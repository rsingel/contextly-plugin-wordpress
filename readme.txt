=== Related Links by Contextly ===
Contributors: rsingel, andrewcgg
Tags: related links, SEO, promotion, related, custom posts, sidebars, similar, recommendations, see also, related stories
Requires at least: 2.0.2
Tested up to: 3.8
Stable tag: trunk
License: GPLv2 or later

Increase pageviews and SEO with the best related and engaging content recommendation widgets and sidebars. Fully automatic to fully manual.

== Description ==

[Contextly](http://contextly.com "Contextly") helps sites keep their readers reading more through content recommendation widgets at the end of stories and in sidebars in the body of posts. We also boost SEO by making it dead simple to add links in the body of your post to previous stories, and old posts get links to new ones.

Sites that take advantage of our sidebars and post-story widgets see clickthrough rates as high as 10.5% - turning drive-by search and social network visitors into loyal readers.

Contextly shows off both related content and your most engaging content in responsive widgets. Readers in research mode can dive deep; those just browsing can go wide. Related content can be chosen by writers or left to our algorithms, or some combination of the two.

You can also recommend videos, as well promote your events, email list and products with a custom row and promotional links.

Choose from 4 responsive designs that look great on large screens and mobile devices.

= Features =

* Easily add SEO-building links to the body of your stories with our one-of-a-kind link choosing tool that lets you link inwards or to the web
* Easily build and add visually appealing sidebars to the body of your posts, including re-using ones you've built before
* Automatic related and interesting links on ALL posts, regardless if you choose related posts for that post. Fully manual for posts where writers chooses related links
* Four responsive visual designs and one text design to choose from
* Customizable image sizes that change on the fly. Served from a CDN to make loading super-fast
* No load on your database
* Point-n-Click customization of displays (fonts, font sizes, colors, sections) and full CSS control
* Awesome analytics emails that tell you how your site (and Contextly's recommendations) are performing
* Reciprocal related links: If Story A links back to Story B, and a reader finds Story B through search, they will see a link to Story A
* Contextly detects internal HTML links in the body of your stories as related links
* Custom post types support
* A/B Testing of widget designs to see which performs best (e.g. You can show 10% of visitors a different design and compare the performance.)
* Add a custom row filled by an RSS feed or hand-chosen links. Show off your YouTube videos, products, or editor's picks.

== Installation ==

1. Install using the Wordpress Gallery for the easiest installation or upload the contextly-related-links folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Create a new post and click on one of our buttons to be taken through the set-up wizard to choose a display and get your API key.
4. Contextly will automatically display related and interesting content suggestions. Advanced options can be found under Settings -> Contextly -> Settings

== Frequently Asked Questions ==

= What will be displayed if I don't choose related posts? =

We've got your back. We'll automatically show related and interesting stories on all posts.

But, let's be honest, humans are still better than computers when it comes to context. As you publish new posts, take the time to add links to the body of your story or choose relevant stories. We make that super easy to do. Your readers will thank you.

= Will Contextly slow down my site? =

No. Our service works outside of Wordpress so we don't slow down your database or your page loads. We also wait to load the sidebars and the after-post widget until the rest of your site load, using asynchronous JavaScipt. And, if for some unforseen reasons Contextly temporarily stops working -- say if zombies destroy our server -- your site will still load quickly.

= How do I use Contextly to promote my event, email list, product, etc? =

Go to Settings -> Contextly -> Settings. Then look for Promo Links. Give us a title and an url of what you want to promote and we'll add it to the section you choose.

= I just installed Contextly and I don't see related posts, just interesting ones. Where are they? =

You discovered something interesting. Reload the story you just looked at and you will see related links. The first time someone visits an older post after you install Contextly, our service starts to analyze that post. The next visitor, even if they show up seconds later, will see related posts. For new posts, every visitor will see related posts, but when you preview a story, you will only see the ones you have chosen, not the ones we'll pick.

= Can I modify the way the content recommendations are displayed on my site? =

Yes. Our service makes it possible to make many changes with a simple point-and-click interface. For those who want more control, you can create your own custom CSS stylesheet or make CSS changes in the settings panel.

= Will I be able to see how well Contextly is doing? =

Yes. You will get a daily email with statistics about your traffic and related links. Contextly links will also show up in Google Analytics and other traffic measurement software.

You can also see what's happening in Google Analytics. We use Events to track clicks. Go to Content -> Events and look for Contextly Sidebar and Contextly Widget. You can dive deep into this.

= How does the Custom Tab work? =

You give us an RSS feed of stuff you want us to show off. This can be a feed of your YouTube videos, a category on your site you like, a set of products, etc. If you just want to choose some items, copy and paste your main RSS feed into the Custom box. Then go to Promo Links and add links you want us to show off and choose the Custom section. Be creative, but there's one limitation: you can't sell this spot to advertisers unless you arrange that with us first. That's because there are certain safeguards against fraud that need to be in place for online ads. Putting external ads in that section without permission is grounds for termination of service.


== Screenshots ==

1. Here's how the Contextly related stories Blocks section looks underneath a published story.
2. Here's a sidebar created in seconds using the plugin.
3. Here's what the interface for choosing stories looks like.
4. Here's what the preview looks like inside the post editor.
5. Here's a sample of what the analytics e-mails look like.


== Changelog ==

= 1.0.97 =

- Updated to include cookies to better track recommendation performance
- Post-story module now movable via shortcode
- Minor display bug fixes


= 1.0.96 =

- Minor display bug fixes


= 1.0.95 =

- Added SEO support. Search Engines can now see and spider the related results. This boosts SEO.
- Minor display bug fixes
- Made the "nudge" to add related links off by default


= 1.0.93 =

- Minor performance tweaks.
- CSS optimization.


= 1.0.91 =

- General performance optimizations
- Ability to turn off pre-publication notification
- CSS optimization.
- Improvements to the JavaScript and CSS for display options, including tweaks for mobile devices


= 1.0.88 =

- Added Auto-Sidebars. If you turn on this option, a sidebar shortcode will be inserted into every new post when you first create it. You can control the content or you can leave it to our related algorithms
- Improvements to the JavaScript and CSS for display options, including tweaks for mobile devices


= 1.0.87 =

- Namespace changes to CSS.
- Changes to make install and activation easier.
- Minor performance tweaks.


= 1.0.86 =

- Minor performance tweaks.


= 1.0.85 =

- Added support for showing Contextly content on Custom Post types. Checkboxes to add/remove post types are in the Contextly Settings Advanced Tab.
- Added Support for 4 responsive designs (Chrome, Blocks, Blocks - Overylay, Float).
- Added design customization for sidebars (Point&Click & custom CSS).
- Added automatic Related Links for stories without chosen links.
- Added responsive sidebars.
- New Single Link and Sidebar Icons in the Visual Editor.
- Added a related links/sidebar reminder before publishing or updating.
- Moved Related Links master button out of Visual Editor. It's now above the Publish button.
- Related Links preview now shows up underneath a post in the editor, instead of in the right sidebar.
- Added optional custom third row based off RSS, including YouTube RSS.
- Added support for pop-over video playback of YouTube links.
- Javascript performance fixes.
- Plugin performance tweaks.
- Secure, direct login to Contextly settings panel from Wordpress (no Twitter credentials needed).
- Google Analytics data now shows up in Events under Content, not via UTM codes. This provides cleaner and more detailed statistics.
- Sidebar links now used to help populate related links by default.
- New, cleaner branding pop-up with privacy policy link.


= 1.0.76 =

- Completely new API
- Ability to add Sidebars into the body of posts
- Support for showing links and sidebars on posts, pages or both
- Option to turn off Contextly content on per page/post basis

= 1.0.67 =

- Core JavaScript files now called over HTTPS
- Changed jQuery check to use built-in Wordpress function.

= 1.0.63 =

- Made a small change to be compatible with sites using Cloudflare

= 1.0.62 =

- Added an easier way to get to the settings page and cleaned up the WP settings page

= 1.0.61 =

- Added a setting in WP to allow sites to place the related links widget in spots other than at the very end of a post
- Small technical changes to better work with Wordpress API




== About Contextly ==

Contextly builds tools that are good for publications, writers and readers. [Contextly](http://contextly.com "Contextly") was founded by veteran online journalist Ryan Singel, a writer and editor for Wired. Our clients include Wired, Cult of Mac, Make Magazine and other great publications. If you have questions, please write us at info@contextly.com.

