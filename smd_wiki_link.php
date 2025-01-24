<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_wiki_link';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.1.0';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'https://stefdawson.com/';
$plugin['description'] = 'Create Textpattern articles from 404s';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '1';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
if (txpinterface === 'admin') {
    register_callback('smd_wiki_inject', 'article', '', 1);
}

if (class_exists('\Textpattern\Tag\Registry')) {
    \Txp::get('\Textpattern\Tag\Registry')
            ->register('smd_wiki_link');
}

/**
 * Public tag to create a wiki link.
 *
 * Use inside a 404 page template.
 * Only supports /section/title permlink modes at present.
 *
 * Thanks to Oleg for the permlink matching concept.
 *
 * @param  string $atts  Tag attributes
 * @param  string $thing Tag contained content
 * @return string        HTML
 */
function smd_wiki_link($atts, $thing = null)
{
    global $pretext, $txp_sections;

    extract(lAtts(array(
        'form' => '',
        'text' => 'Create article',
    ), $atts));

    $out = '';
    $text = $form ? parse_form($form) : ($thing ? parse($thing) : $text);
    $now = time();

    $detected_section = '';
    $page_url = trim($pretext['req'], '/');
    $url_title = $pretext[$pretext[0]];
    $id = 0; // Used to defeat cache

    foreach ($txp_sections as $section => $data) {
        $urlid = 0;

        // Can only detect these permlink schemes + /section/title reliably.
        // Anything with category, breadcrumb or ?messy is ignored.
        if ($data['permlink_mode'] === 'year_month_day_title' && $pretext[0] === 4) {
            $now = safe_strtotime($pretext[1].'/'.$pretext[2].'/'.$pretext[3]);
        } elseif ($data['permlink_mode'] === 'section_id_title' && $pretext[0] === 3) {
            $urlid = intval($pretext[2]);
        } elseif ($data['permlink_mode'] === 'id_title' && $pretext[0] === 2) {
            $urlid = intval($pretext[1]);
        }

        $article_array = array('section' => $section, 'uposted' => $now, 'url_title' => $url_title, 'id' => $urlid ? $urlid : --$id);
        $url = permlinkurl($article_array, '');

        // First match might be wrong, but nothing we can do about it.
        if (trim($url, '/') == $page_url) {
            $detected_section = $section;
            break;
        }
    }

    $match_section = ($detected_section) ? '&Section='.txpspecialchars($detected_section) : '';
    $safe_url_title = sanitizeForUrl($url_title);
    $escaped_title = txp_escape('title', str_replace(array('-', '_'), ' ', $url_title));
    $anchor = ahu . '?event=article&smd_wiki=true' . $match_section . '&url_title=' . $safe_url_title . '&Title=' . $escaped_title;

    if (class_exists('\Textpattern\UI\Anchor')) {
        $out = \Txp::get('\Textpattern\UI\Anchor', $text, $anchor);
    } else {
        $out = href($text, $anchor);
    }

    return $out;
}

/**
 * Inject the passed params into the current (blank) article
 *
 * @param  string $evt Textpattern event
 * @param  string $stp Textpattern step
 */
function smd_wiki_inject($evt, $stp)
{
    if ($evt === 'article' && gps('smd_wiki')) {
        extract(gpsa(array('url_title', 'Section', 'Title')));
        echo script_js(<<<EOJS
document.addEventListener("DOMContentLoaded", function() {
    let urlFld = document.getElementById('url-title');
    let ttlFld = document.getElementById('title');
    let secFld = document.getElementById('section');
    urlFld.value = '{$url_title}';
    ttlFld.value = '{$Title}';
    if ('{$Section}' !== '') {
        secFld.value = '{$Section}';
    }
});
EOJS
            );
    }

}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_wiki_link

Rapidly create articles from, as yet, un-linked articles, similar to DocuWiki.

To use:

Step 1
Edit your error_default or error_404 page and add:

bc. <txp:if_logged_in>
    <txp:smd_wiki_link />
</txp:if_logged_in>

Step 2
Now, when you're creating articles, if you make an anchor link to an article that doesn't yet exist, you can click it. This will trigger your 404 page template, which will then be intercepted by the plugin when you're logged in and offer a link directly to the Write panel to create it.

The following fields will be automatically populated for you if you are using one of the compatible URL schemes:

* The url_title will be used as-is (after being sanitized) so be sure to restrict the link to alphanumeric characters and underscore/hyphen to avoid broken links.
* The Title will be constructed by converting the url_title into distinct words at hyphen/underscore and then convert each word to Title Case.
* The Section will be selected based on the section in which the destination link you clicked on is going to reside. If the plugin cannot detect the section, it will use the default section.

At this point you can write the rest of the article, Publish it and your original link will then work on your live site.

The smd_wiki_link tag accepts a @text@ attribute to customise the anchor text link. It defaults to 'Create article'. You may also use a @form@ or provide some container content to populate the anchor text.

# --- END PLUGIN HELP ---
-->
<?php
}
?>