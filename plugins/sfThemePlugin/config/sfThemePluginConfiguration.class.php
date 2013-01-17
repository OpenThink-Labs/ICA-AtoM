<?php

/**
 * sfThemePlugin configuration.
 *
 * @package     sfThemePlugin
 * @subpackage  config
 * @author      Your name here
 * @version     SVN: $Id: sfThemePluginConfiguration.class.php 2409 2009-04-25 03:10:19Z jablko $
 */
class sfThemePluginConfiguration extends sfPluginConfiguration
{
  /**
   * @see sfPluginConfiguration
   */
  public function initialize()
  {
    $enabledModules = sfConfig::get('sf_enabled_modules');
    $enabledModules[] = 'sfThemePlugin';
    sfConfig::set('sf_enabled_modules', $enabledModules);
  }
}
