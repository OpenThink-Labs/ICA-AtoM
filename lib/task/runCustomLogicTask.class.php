<?php

/*
 * This file is part of Qubit Toolkit.
 *
 * Qubit Toolkit is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Qubit Toolkit is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Qubit Toolkit.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Run ad-hoc PHP logic contained in a file
 *
 * @package    symfony
 * @subpackage task
 * @author     Mike Cantelon <mike@artefactual.com>
 * @version    SVN: $Id: csvImportTask.class.php 10797 2012-02-02 19:59:06Z mcantelon $
 */
class runCustomLogicTask extends sfBaseTask
{
    protected $namespace        = 'tools';
    protected $name             = 'run';
    protected $briefDescription = 'Run ad-hoc logic contained in a PHP file';

    protected $detailedDescription = <<<EOF
Run ad-hoc logic contained in a PHP file
EOF;

  /**
   * @see sfBaseTask
   */
  protected function configure()
  {
    $this->addArguments(array(
      new sfCommandArgument(
        'filename',
        sfCommandArgument::REQUIRED,
        'The custom logic file (containing PHP logic).'
      )
    ));

    // TODO: add capability to define ad-hoc arguments
  }

  /**
   * @see sfTask
   */
  public function execute($arguments = array(), $options = array())
  {
    if (false === $fh = fopen($arguments['filename'], 'rb'))
    {
      throw new sfException('You must specify a valid filename');
    }

    // initialized data connection in case it's needed
    $databaseManager = new sfDatabaseManager($this->configuration);
    $conn = $databaseManager->getDatabase('propel')->getConnection();

    include($arguments['filename']);
  }
}
