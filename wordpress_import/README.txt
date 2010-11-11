This Drupal module imports a Wordpress blog using the WXR files provided by recent Worpdress versions.

Documentation can be found at http://www.rocq.net/yann/en/projets/drupal/wordpress_import/documentation_en

Author: Yann Rocq (http://www.rocq.net/yann/)

System requirements:
- PHP 5
- Drupal 5
- Trackback module (http://drupal.org/project/trackback)
- Wordpress >= 2.1


Usage:
As its name indicates, the Wordpress Import module can import a Wordpress blog previously exported to WXR format. These steps must be followed to achieve this goal:

In Wordpress
- Go to the administration zone of your blog.
- Choose tab “Manage”, and the sub-tab “Export”.
- Using the drop-down menu, select whether you want to export all of your blog entries or only posts of one author.
- Click the button “Download Export File”
- Downloading of the export file on your machine starts automatically.

In Drupal
 - Unzip the archive of the module and copy the directory wordpress_import obtained in the directory sites/all/modules of your Drupal installation.
 - Log in to your site as super user (first user created in Drupal)
 - Activate the module in the administration page
 - Click on the new menu “Administer / content management / Wordpress Import”
 - Click on “Browse…” then select the file WXR previously downloaded from Wordpress
 - Click on “Next”
 - In the section “Users mapping”, choose how you want Drupal users to match each Wordpress author
 - Click on “Next”
 - Finished

Known issues:
- Blog must be encoded into UTF-8
- No rollback if something wrong happens. You should make a backup of your database before importing a blog.
- Importing a blog can take a lot of time and memory. You may have to modify the memory_limit and  max_execution_time variables for big blogs (see http://www.php.net/ini.core).

Thanks to the following contibutors for their patches and feedback : Henriette, Andrew Turner, VladSavitsky, Irina Zaks
