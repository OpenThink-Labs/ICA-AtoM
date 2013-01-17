// $Id: flowplayer.js 6875 2010-05-13 00:31:07Z jablko $

(function ($)
  {
    Drupal.behaviors.flowplayer = {
      attach: function (context)
        {
          $('.flowplayer', context).each(function ()
            {
              flowplayer(this, Qubit.relativeUrlRoot + '/vendor/flowplayer/flowplayer-3.1.5.swf', { clip: { autoPlay: false } });
            });
        }};
  })(jQuery);
