/*$Id: image_caption.js,v 1.2 2008/03/07 05:46:23 davidwhthomas Exp $*/
$(document).ready(function(){
  $("img.caption,img.imagecache-content_image").each(function(i) {
    var imgwidth = $(this).width();
    var imgheight = $(this).height();
    var captiontext = $(this).attr('alt');
    var alignment = $(this).attr('align');
	var classes = $(this).attr('class');
    $(this).attr({align:""});
    $(this).wrap("<div class=\"image-caption-container " + classes + "\" style=\"float:" + alignment + "\"></div>");
    $(this).parent().width(imgwidth);
    $(this).parent().append("<div class=\"image-caption\">" + captiontext + "</div>");
  });
});