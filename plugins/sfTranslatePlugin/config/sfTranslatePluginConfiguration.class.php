<?php

/**
 * sfTranslatePlugin configuration.
 *
 * @package     sfTranslatePlugin
 * @subpackage  config
 * @author      Your name here
 * @version     SVN: $Id: sfTranslatePluginConfiguration.class.php 12129 2012-08-17 17:19:59Z david $
 */
class sfTranslatePluginConfiguration extends sfPluginConfiguration
{
  public function contextLoadFactories(sfEvent $event)
  {
    $context = $event->getSubject();

    // Stop execution if user is not authenticated
    if (!$context->user->isAuthenticated())
    {
      return;
    }

    $context->response->addJavaScript('/vendor/jquery');
    $context->response->addJavaScript('/plugins/sfDrupalPlugin/vendor/drupal/misc/jquery.once.js');
    $context->response->addJavaScript('/plugins/sfDrupalPlugin/vendor/drupal/misc/drupal');
    $context->response->addJavaScript('/plugins/sfDrupalPlugin/vendor/drupal/misc/textarea');
    $context->response->addJavaScript('/plugins/sfTranslatePlugin/js/l10n_client');

    $context->response->addStylesheet('/plugins/sfTranslatePlugin/css/l10n_client');
  }

  /**
   * @see sfPluginConfiguration
   */
  public function initialize()
  {
    $this->dispatcher->connect('context.load_factories', array($this, 'contextLoadFactories'));

    $enabledModules = sfConfig::get('sf_enabled_modules');
    $enabledModules[] = 'sfTranslatePlugin';
    sfConfig::set('sf_enabled_modules', $enabledModules);
  }
}
