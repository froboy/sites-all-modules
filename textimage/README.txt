// $Id: README.txt,v 1.4.6.1 2009/05/14 03:55:13 deciphered Exp $

Textimage adds text to image functionality using GD2 and Freetype, enabling
users to create crisp images on the fly for use as theme objects, headings or
limitless other possibilities.

Textimage was written by Fabiano Sant'Ana (wundo).
- http://wundo.net

Maintained by Stuart Clark (Deciphered).
- http://stuar.tc/lark


Features
------------

* Support for TrueType fonts and OpenType fonts.
* Rotate your text at any angle.
* Configurable opacity in text color.
* Backgrounds:
  * Define a color or simply have a transparent background.
  * Use a pre-made image to integrate directly with your theme.
  * Use another Textimage preset to achieve a multi-layered image (see image above).
* CCK and Views formatter integration.
* Support for non-alphanumeric characters.


Usage
------------

1. via theme_textimage_image():

   Use the theme_textimage_image() function at the theme/module level with the
   following format:

   theme('textimage_image', 'Preset', 'Text', array('Additional', 'Text'),
   'extension', 'alt', 'title')


2. via CCK/Views formatter:

   Select a Textimage preset in a text field display options.


3. via URL:

   Create an image with the URL in following format:
   /[files directory]/textimages/[Preset](/Additional/Text)/[Text].[extension]

   Note: This method can only be used by users with the 'create textimages'
   permission. This is to prevent Anonymous users from creating random images.

   If you need dynamically created Textimages, it is strongly advised you use
   one of the methods detailed above.


Requirements
------------

* GD2
* FreeType


Recommended
------------

* Vertical Tabs - http://drupal.org/project/vertical_tabs

  When the Vertical Tabs module is enabled you will receive a modified user
  interface when creating and editing Textimage presets.


Updating
------------

* Always run update.php on your Drupal site after updating Textimage.
* Note: Due to certain changes in the Textimage module, some of your presets may
  require alterations after updating.

