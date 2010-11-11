WP2Drupal

With this module you can import your site from Wordpress to a clean Drupal
install.


Ancestory

Originally created by Borken Bernard for Drupal 4.7, it was updated to support
Drupal 5 by teodorani and then upgraded to work with Drupal 6 by DenRaf. This
a continuation of DenRaf's code from his blog announcement on June 19th, 2008.

Note: While the project has been available in versions for Drupal 5 and older,
only Drupal 6 and newer will be supported.


Goals

The goals of the project are simple:
* Provide a method of importing a Wordpress blog into Drupal.
* Make it feature complete for all core Wordpress data.
* Make it easy to use.
* Long term goal: merge it into an all-in-one migration tool, e.g. maybe
  Migrate.


In Comparison to Wordpress_Import

The Wordpress_Import module work towards a similar goal, but there are several
differences:

* Wordpress_Import uses an WXR export file from Wordpress whereas WP2Drupal
  interacts directly with the database.
* WP2Import has greater flexibility with the import:
  * You can decide what content types to use for posts & pages
  * You can decide what vocabularies to use for categories and tags
  * You can use Path_Redirect to catch the old URLs but still use PathAuto to
    give them new URLs; GlobalRedirect will make it even more transparent
  * You can (optionally) clear out (truncate) existing content tables to start
    with a fresh copy of your content
* Wordpress_Import is somewhat more user-friendly.

Ultimately, though, WP2Drupal will be merged into a more useful all-in-one
migration tool.


Todo:
* Simpler user importing.
* Blogroll (#432338).
* Wordpress 2.3 - tags (done), "pending" status (#431064).
* Wordpress 2.4 - skipped in favor of 2.5.
* Wordpress 2.5 - no relevant DB-related changes.
* Wordpress 2.6 - post revisions (#431068).
* Wordpress 2.7 - comment threading (#431072).


History:
0.1 - Initial launch of old code from DenRaf.
1.0 - Code now actually works in Drupal 6.
1.1 - #431056 - Use Path_Redirect to handle old URLs.
    - #431328 - Remove PHP4 support.
1.2 - #431890 - Change order of code so truncate happens before redirects.
    - #431790 - "Node type for Wordpress static pages" should be a selector.
    - #431896 - Remove references to sequences table.
    - For now, does not import the link_categories taxonomy.
    - For now, does not import the post revisions.
    - #431058 - Separate how categories and tags are handled.
1.3 - #432286 - Some categories/tags are being skipped.
1.4 - Hide the extra rewrite fields when using GlobalRedirect.
    - Added some Before You Begin info at the top of the form.
    - Corrected references to GlobalRedirect, it's Path_Redirect that does the
      actual work, GlobalRedirect just makes it cleaner.
    - Changed to using the correct drupal_write_record() function to insert
      the path_redirect lines.
    - Skip importing terms for WP posts records that were not imported, e.g.
      attachments, revisions.
    - #433932 - Fixed a bug in the taxonomy importer that confused the WP
      term_id and term_taxonomy_id.
1.5 - Removed an incorrect comment regarding re-running the Results to continue
      the import process, it starts it over from scratch again if you set the
      tables to be truncated.
    - Assign WP user ID 0 to Drupal user ID 1, a work-around for some faulty
      WP modules.
    - #435116 - Not distinguishing between posts and pages.
    - Rewrote _wp_path() to use post['guid'] and options['siteurl'], making it
      much faster; left the previous code for backwards compatibility.
    - #432340 - Category and tag page URLs are now converted if path_redirect
      is installed.
1.6 - #436700 - Syntax errors.
