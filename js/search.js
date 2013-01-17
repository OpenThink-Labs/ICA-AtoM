// $Id: search.js 6875 2010-05-13 00:31:07Z jablko $

Drupal.behaviors.search = {
  attach: function (context)
    {
      $('input.search', context).each(function ()
        {
          var input = this;

          $(this.form).hide();

          $(':submit', this.form)
            .click(function (event)
              {
                event.preventDefault();

                $(input).val($('#search-sidebar :text').val());

                $(input.form).submit();
              })
            .insertBefore('#search-sidebar :submit');
        });
    } };
