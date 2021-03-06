// $Id: README.txt,v 1.7.2.1.2.3 2009/08/18 06:49:08 xamanu Exp $

DESCRIPTION
-----------
Timeline is a DHTML-based AJAXy widget for visualizing temporal information.
It works in a similar way as Google Maps but for time-based events.

This Drupal module is based on a JavaScript widget developed by the MIT
SIMILE project. Several demo timelines demonstrating the full range of 
capabilities for this widget are available on the SIMILE project site at:
- http://code.google.com/p/simile-widgets/

This module is a style plugin for the Views module that represents date 
information of nodes on horizontal timeline display.
- http://drupal.org/project/views

The timeline provides support for the Date module. CCK date fields can be used
to display all kinds of information on the timeline.
- http://drupal.org/project/cck
- http://drupal.org/project/date

The SIMILE Timeline (and Ajax) libraries can be used from the SIMILE server 
or can be downloaded and installed into your (local) Drupal installation using 
the libraries module. (See INSTALL.txt for more information). By default, the 
SIMILE Timeline (and Ajax) libraries can be used from the SIMILE server.
- http://drupal.org/project/libraries

A view is provided by default to get you started quickly: 
* timeline_nodes: plots any nodes by their created date

Timeline events with only a start date are displayed as points on the
timeline view, whilst events with both a start and date are displayed as
continuous colored blocks (not dissimilar to Gantt chart entries).


REQUIREMENTS
------------
Please refer to the accompanying INSTALL.txt file.


INSTALLATION
------------
Please refer to the accompanying INSTALL.txt file.


USAGE
-----
After installing the module, do the following to create a new timeline view:

1. Make sure the `views' and `views_ui' modules are installed and enabled.
2. Go to administer >> views >> add.
3. Make a new view that is of the view type "Node"
4. Under Fields, add that fields that you would like to have populate the title, 
   start and end dates and body of the timeline popup.
   Note: Make sure that you are using exclusive date fields. Don't choose "Show from and to date"
5. Set any other views settings, such as the maximum number of nodes to
   show, filters to select only certain nodes or dates, etc. You'll likely
   want to disable the view's pager option in order to get all events to
   show up on the timeline.
6. Under "Basic settings" >> "Style" select the option "Timeline".
7. Configure the Timeline display (click the gear next to the style selector). 
   Set the orientation, display and field settings that control how the 
   timeline looks and behaves. 
   Please take special care when selecting the "field usage". These fields let you map
   the fields selected in your view to the fields used by the timeline.
8. Save your view and navigate to it to test out the timeline. In case you
   don't see the timeline at all, that most likely indicates the view
   selection criteria didn't result in any nodes to be displayed.


CREDITS
---------------
Version 2.x 
Maintained by Felix Delattre [1] with kindly support of Jeff Miccolis (Development Seed) [2].
The included Fancy Theme is based on work by Shawn Kindling [3]

Version 1.x 
Originally created by David Donohue [4] and further developed by Arto Bendiken [5] and 
Jeff Miccolis (Development Seed) [2].


Both timelines are based on the Timeline developed by the SIMILE project [6]


[1] Felix Delattre -> xamanu -> http://drupal.org/user/359937
[2] Jeff Miccolis -> jmiccolis -> http://drupal.org/user/31731
[3] Shawn Kindling -> http://keelingdesign.com/theming_a_simile_timeline
[4] David Donohue -> dado -> http://drupal.org/user/16745
[5] Art Bendiken -> arto -> http://drupal.org/user/26089
[6] SIMILE project -> http://code.google.com/p/simile-widgets/


TROUBLESHOOTING
---------------
To post bug reports or feature requests please use the following issue
trackers for the Drupal module and the MIT SIMILE widget, respectively:
- http://drupal.org/project/issues/timeline
- http://code.google.com/p/simile-widgets/issues/list
