// $Id: actions.js 6875 2010-05-13 00:31:07Z jablko $

Drupal.behaviors.actions = {
  attach: function (context)
    {
      $('.actions').each(function ()
        {
          var img = $('<img src="' + Qubit.relativeUrlRoot + '/plugins/sfDrupalPlugin/vendor/drupal/misc/menu-expanded.png"/>').replaceAll(this).get(0);

          // HACK: YAHOO.widget.Menu() requires a string argument
          var menu = new YAHOO.widget.Menu(Math.random().toString(), { context: [img, 'tl', 'bl' ] });

          $('li a', this).each(function ()
            {
              menu.addItem({ text: $(this).text(), url: $(this).attr('href') });
            });

          menu.render(img.parentNode);

          $(img).click(function ()
            {
              menu.show();
            });
        });
    } };
