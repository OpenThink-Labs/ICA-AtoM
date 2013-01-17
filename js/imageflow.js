// $Id: imageflow.js 6875 2010-05-13 00:31:07Z jablko $

(function ($)
  {
    Drupal.behaviors.imageflow = {
      attach: function (context)
        {
          $('.imageflow', context).each(function ()
            {
              new ImageFlow().init({
                opacity: true,
                reflectionP: 0,
                reflections: false });
            });
        } };
  })(jQuery);
