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
 * Import csv authoriy record data
 *
 * @package    symfony
 * @subpackage task
 * @author     Mike Cantelon <mike@artefactual.com>
 * @version    SVN: $Id: csvImportTask.class.php 10666 2012-01-13 01:13:48Z mcantelon $
 */
class csvAuthorityRecordImportTask extends csvImportBaseTask
{
  protected $namespace        = 'csv';
  protected $name             = 'authority-import';
  protected $briefDescription = 'Import csv authority record data';
  protected $detailedDescription = <<<EOF
Import CSV data
EOF;

  /**
   * @see csvImportBaseTask
   */
  protected function configure()
  {
    parent::configure();

    $this->addOptions(array(
      new sfCommandOption('alias-file', null, sfCommandOption::PARAMETER_OPTIONAL, 'CSV file containing aliases.'),
      new sfCommandOption('relation-file', null, sfCommandOption::PARAMETER_OPTIONAL, 'CSV file containing relationships.'),
      new sfCommandOption('source-name', null, sfCommandOption::PARAMETER_OPTIONAL, 'Source name to use when inserting keymap entries.')
    ));
  }

  /**
   * @see sfTask
   */
  public function execute($arguments = array(), $options = array())
  {
    $this->validateOptions($options);

    $skipRows = ($options['skip-rows']) ? $options['skip-rows'] : 0;

    $sourceName = ($options['source-name'])
      ? $options['source-name']
      : basename($arguments['filename']);

    // if alias file option set, load aliases from CSV
    $aliases = array();

    if ($options['alias-file'])
    {
      // open alias CSV file
      if (false === $fh = fopen($options['alias-file'], 'rb'))
      {
        throw new sfException('You must specify a valid filename');
      } else {
        print "Reading aliases\n";

        // import name aliases, if specified
        $import = new QubitFlatfileImport(array(
          'status' => array(
            'aliases' => array()
          ),
          'ignoreColumns' => array(
            'RecordID'
          ),
          'variableColumns' => array(
            'parentAuthority',
            'OtherName',
            'email'
          ),
          'saveLogic' => function(&$self)
          {
            if (trim($self->rowStatusVars['OtherName']))
            {
              $aliases = $self->getStatus('aliases');
              $aliases[] = array(
                'authoritative' => $self->rowStatusVars['parentAuthority'],
                'otherName'     =>$self->rowStatusVars['OtherName']
              );
              $self->setStatus('aliases', $aliases);
            }
          }
        ));
      }
      $import->csv($fh);
      $aliases = $import->getStatus('aliases');
    }

    if (false === $fh = fopen($arguments['filename'], 'rb'))
    {
      throw new sfException('You must specify a valid filename');
    }

    $databaseManager = new sfDatabaseManager($this->configuration);
    $conn = $databaseManager->getDatabase('propel')->getConnection();

    // Load taxonomies into variables to avoid use of magic numbers
    $termData = QubitFlatfileImport::loadTermsFromTaxonomies(array(
      QubitTaxonomy::NOTE_TYPE_ID => 'noteTypes',
      QubitTaxonomy::ACTOR_ENTITY_TYPE_ID => 'actorTypes',
      QubitTaxonomy::ACTOR_RELATION_TYPE_ID => 'actorRelationTypes'
    ));

    // Define import
    $import = new QubitFlatfileImport(array(
      /* What type of object are we importing? */
      'className' => 'QubitActor',

      /* How many rows should import until we display an import status update? */
      'rowsUntilProgressDisplay' => $options['rows-until-update'],

      /* Where to log errors to */
      'errorLog' => $options['error-log'],

      /* the status array is a place to put data that should be accessible
         from closure logic using the getStatus method */
      'status' => array(
        'sourceName' => $sourceName,
        'actorTypes' => $termData['actorTypes'],
        'aliases'    => $aliases,
        'actorNames'  => array()
      ),

      /* import columns that map directory to QubitInformationObject properties */
      'standardColumns' => array(
        'history'
      ),

      /* import columns that should be redirected to QubitInformationObject
         properties (and optionally transformed)
      
         Example:
         'columnMap' => array(
           'Archival History' => 'archivalHistory',
           'Revision history' => array(
             'column' => 'revision',
             'transformationLogic' => function(&$self, $text)
             {
               return $self->appendWithLineBreakIfNeeded(
                 $self->object->revision,
                 $text
               );
             }
           )
         ),
      */
      'columnMap' => array(
        'name' => 'authorizedFormOfName',
        'dates' => 'datesOfExistence'
      ),

      /* import columns that can be added as QubitNote objects */
      'noteMap' => array(
        'maintenanceNotes' => array(
          'typeId' => array_search('Maintenance note', $termData['noteTypes'])
        )
      ),

      /* these values get stored to the rowStatusVars array */
      'variableColumns' => array(
        'EntityType',
        'email',
        'notes',
        'countryCode',
        'fax',
        'telephone',
        'postalCode',
        'streetAddress',
        'region'
      ),

      /* import logic to execute before saving actor */
      'preSaveLogic' => function(&$self)
      {
        if ($self->object)
        {
          if (
            isset($self->rowStatusVars['EntityType'])
            && $self->rowStatusVars['EntityType']
          )
          {
            $entityTypes  = $self->getStatus('actorTypes');
            $entityType   = ucfirst(strtolower($self->rowStatusVars['EntityType']));
            $entityTypeId = array_search($entityType, $entityTypes);
            if ($entityTypeId)
            {
              $self->object->entityTypeId = $entityTypeId;
            } else {
              throw new sfException($entityType .' is not a valid actor entity type.');
            }
          }
        }
      },

      /* import logic to execute after saving actor */
      'postSaveLogic' => function(&$self)
      {
        if ($self->object)
        {
          // note actor name for optional relationship import phase
          $self->status['actorNames'][$self->object->id] = $self->object->authorizedFormOfName;

          // cycle through aliases looking for other names
          $otherNames = array();
          $aliases = $self->getStatus('aliases');
          foreach($aliases as $alias)
          {
            if ($self->object->authorizedFormOfName == $alias['authoritative'])
            {
              // add other name
              $otherName = new QubitOtherName;
              $otherName->objectId = $self->object->id;
              $otherName->name = $alias['otherName'];
              $otherName->typeId = QubitTerm::OTHER_FORM_OF_NAME_ID;
              $otherName->save();
            }
          }

          // add contact information, if applicable
          $contactVariables = array(
            'email',
            'notes',
            'countryCode',
            'fax',
            'telephone',
            'postalCode',
            'streetAddress',
            'region'
          );

          $hasContactInfo = false;
          foreach(array_keys($self->rowStatusVars) as $name)
          {
            if (in_array($name, $contactVariables))
            {
              $hasContactInfo = true;
            }
          }

          if ($hasContactInfo)
          {
            // add contact information
            $info = new QubitContactInformation();
            $info->actorId = $self->object->id;

            foreach($contactVariables as $property)
            {
              if ($self->rowStatusVars[$property])
              {
                $info->$property = $self->rowStatusVars[$property];
              }
            }

            $info->save();
          }
        }
      }
    ));
    $import->csv($fh, $skipRows);
    $actorNames = $import->getStatus('actorNames');

    // optional relationship import
    if ($options['relation-file'])
    {
      // open relationship CSV file
      if (false === $fh = fopen($options['relation-file'], 'rb'))
      {
        throw new sfException('You must specify a valid filename');
      } else {
        print "Importing relationships\n";

        $import = new QubitFlatfileImport(array(
          'status' => array(
            'actorNames'         => $actorNames,
            'actorRelationTypes' => $termData['actorRelationTypes']
          ),
          'ignoreColumns' => array(
            'RecordID'
          ),
          'variableColumns' => array(
            'Source_Name',
            'Target_Name',
            'Relationship_Category',
            'Relationship_Date',
            'Relationship_StartDate',
            'Relationship_EndDate',
            'Relationship_Description'
          ),
          'saveLogic' => function(&$self)
          {
            // figure out ID of the two actors
            $sourceActorId = array_search($self->rowStatusVars['Source_Name'], $self->status['actorNames']);
            $targetActorId = array_search($self->rowStatusVars['Target_Name'], $self->status['actorNames']);

            // determine type ID of relationship type
            $relationTypeId = array_search(
              $self->rowStatusVars['Relationship_Category'],
              $self->status['actorRelationTypes']
            );

            if (!$relationTypeId)
            {
              // throw new sfException('Unknown relationship type :'. $self->rowStatusVars['Relationship_Category']);
            } else {

              // determine type ID of relationship type
              // add relationship, with date/startdate/enddate/description
              if (!$sourceActorId || !$targetActorId)
              {
                $badActor = (!$sourceActorId)
                  ? $self->rowStatusVars['Source_Name']
                  : $self->rowStatusVars['Target_Name'];

                $error = 'Actor "'. $badActor .'" does not exist';
                print $self->logError($error);
              } else {
                $relation = new QubitRelation;
                $relation->subjectId = $sourceActorId;
                $relation->objectId  = $targetActorId;
                $relation->typeId    = $relationTypeId;

                if ($self->rowStatusVars['Relationship_Date'])
                {
                  $relation->date = $self->rowStatusVars['Relationship_Date'];
                }
                if ($self->rowStatusVars['Relationship_StartDate'])
                {
                  $relation->startDate = $self->rowStatusVars['Relationship_StartDate'];
                }
                if ($self->rowStatusVars['Relationship_EndDate'])
                {
                  $relation->endDate = $self->rowStatusVars['Relationship_EndDate'];
                }
                if ($self->rowStatusVars['Relationship_Description'])
                {
                  $relation->description = $self->rowStatusVars['Relationship_Description'];
                }

                $relation->save();
              }
            }
          }
        ));
        $import->csv($fh);
      }
    }
  }
}
