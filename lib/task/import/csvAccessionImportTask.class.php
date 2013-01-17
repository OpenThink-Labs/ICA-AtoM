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
 * Import csv accession data
 *
 * @package    symfony
 * @subpackage task
 * @author     Mike Cantelon <mike@artefactual.com>
 * @version    SVN: $Id: csvImportTask.class.php 10666 2012-01-13 01:13:48Z mcantelon $
 */
class csvAccessionImportTask extends csvImportBaseTask
{
  protected $namespace        = 'csv';
  protected $name             = 'accession-import';
  protected $briefDescription = 'Import csv acession data';
  protected $detailedDescription = <<<EOF
Import CSV data
EOF;

  /**
   * @see sfTask
   */
  protected function configure()
  {
    parent::configure();

    $this->addOptions(array(
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

    if (false === $fh = fopen($arguments['filename'], 'rb'))
    {
      throw new sfException('You must specify a valid filename');
    }

    $databaseManager = new sfDatabaseManager($this->configuration);
    $conn = $databaseManager->getDatabase('propel')->getConnection();

    // Load taxonomies into variables to avoid use of magic numbers
    $termData = QubitFlatfileImport::loadTermsFromTaxonomies(array(
      QubitTaxonomy::ACCESSION_ACQUISITION_TYPE_ID  => 'acquisitionTypes',
      QubitTaxonomy::ACCESSION_RESOURCE_TYPE_ID     => 'resourceTypes',
      QubitTaxonomy::ACCESSION_PROCESSING_STATUS_ID => 'processingStatus'
    ));

    // Define import
    $import = new QubitFlatfileImport(array(
      /* How many rows should import until we display an import status update? */
      'rowsUntilProgressDisplay' => $options['rows-until-update'],

      /* Where to log errors to */
      'errorLog' => $options['error-log'],

      /* the status array is a place to put data that should be accessible
         from closure logic using the getStatus method */
      'status' => array(
        'sourceName'       => $sourceName,
        'acquisitionTypes' => $termData['acquisitionTypes'],
        'resourceTypes'    => $termData['resourceTypes'],
        'processingStatus' => $termData['processingStatus']
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
        'TITLE'             => 'title',
        'LOCATION'          => 'locationInformation',
        'SCOPE AND CONTENT' => 'scopeAndContent',
        'scopeAndContent'   => 'scopeAndContent',
        'physicalCondition' => 'physicalCharacteristics',
        'CONSERVATION NOTE' => 'physicalCharacteristics',
        'extent'            => 'receivedExtentUnits',
        'primaryNotes'      => 'notes',
        'notes'             => 'notes'
      ),

      /* these values get stored to the rowStatusVars array */
      'variableColumns' => array(
        'ACCESSION NUMBER',
        'TYPE',
        'DONOR',
        'EMAIL',
        'POSTAL CODE',
        'TELEPHONE',
        'ADDRESS_Street',
        'ADDRESS_City',
        'ADDRESS_Province',
        'APPRAISAL NOTES',
        'DISPOSITION NOTE',
        'DATE OF CREATION',
        'DEPARTMENT',
        'CREATOR or COLLECTOR'
      ),

      /* import logic to load accession */
      'rowInitLogic' => function(&$self)
      {
        $accessionNumber =  $self->rowStatusVars['ACCESSION NUMBER'];

        // look up Qubit ID of pre-created accession
        $statement = $self->sqlQuery(
          "SELECT id FROM accession WHERE identifier=?",
          $params = array($accessionNumber)
        );

        $result = $statement->fetch(PDO::FETCH_OBJ);
        if ($result)
        {
          print 'Found '. $result->id ."\n";
          $self->object = QubitAccession::getById($result->id);
        } else {
          $self->object = false;
          $error = "Couldn't find accession # ". $accessionNumber .'... creating.';
          print $error ."\n";
          $self->object = new QubitAccession();
          $self->object->identifier = $accessionNumber;
        }
      },

      /* import logic to execute before saving accession */
      'preSaveLogic' => function(&$self)
      {
        if ($self->object)
        {
          if (
            isset($self->rowStatusVars['DATE OF CREATION'])
            && $self->rowStatusVars['DATE OF CREATION'])
          {
            $self->object->scopeAndContent = $self->appendWithLineBreakIfNeeded(
              $self->object->scopeAndContent,
              'Dates of Creation: '. $self->rowStatusVars['DATE OF CREATION']
            );
          }

          if (
            isset($self->rowStatusVars['CREATOR or COLLECTOR'])
            && $self->rowStatusVars['CREATOR or COLLECTOR']
          )
          {
            $creators = explode('|', $self->rowStatusVars['CREATOR or COLLECTOR']);
            foreach($creators as $creator)
            {
              $self->object->scopeAndContent = $self->appendWithLineBreakIfNeeded(
                $self->object->scopeAndContent,
                'Creator: '. trim($creator)
              );
            }
          }

          if (
            isset($self->rowStatusVars['DEPARTMENT'])
            && trim($self->rowStatusVars['DEPARTMENT'])
          )
          {
            $self->object->scopeAndContent = $self->appendWithLineBreakIfNeeded(
              $self->object->scopeAndContent,
              'Creator: '. trim($self->rowStatusVars['DEPARTMENT'])
            );
          }

          if (isset($self->rowStatusVars['receivedExtentUnits']))
          {
            $self->object->receivedExtentUnits = $self->rowStatusVars['receivedExtentUnits'];
          }

          if (isset($self->rowStatusVars['processingNotes']))
          {
            $self->object->processingNotes = $self->rowStatusVars['processingNotes'];
          }

          // amalgamate appraisal-related fields
          $appraisalVarPrefixMap = array(
            'DISPOSITION NOTE' => '',
            'APPRAISAL NOTES'  => 'Appraisal Notes: '
          );

          // if either of the appraisal-related fields contain content,
          // add content to appraisal field, prefixing if necessary
          foreach($appraisalVarPrefixMap as $var => $prefix)
          {
            if (isset($self->rowStatusVars[$var]) && $self->rowStatusVars[$var])
            {
              $self->object->appraisal = $self->appendWithLineBreakIfNeeded(
                $self->object->appraisal,
                $prefix . $self->rowStatusVars[$var]
              );
            }
          }
        }
      },

      /* import logic to save accession */
      'saveLogic' => function(&$self)
      {
        if(isset($self->object) && is_object($self->object))
        {
          $self->object->save();
        }
      },

      /* create related objects */
      'postSaveLogic' => function(&$self)
      {
        if(isset($self->object) && is_object($self->object))
        {
          if (
            isset($self->rowStatusVars['DATE OF CREATION'])
            && $self->rowStatusVars['DATE OF CREATION']
          )
          {
            $self->object->scopeAndContent = $self->appendWithLineBreakIfNeeded(
              $self->object->scopeAndContent,
              'Dates of Creation: '. $self->rowStatusVars['DATE OF CREATION']
            );
          }

          if (
            isset($self->rowStatusVars['DONOR'])
            && $self->rowStatusVars['DONOR']
          )
          {
            // fetch/create actor
            $actor = $self->createOrFetchActor($self->rowStatusVars['DONOR']);

            // map column names to QubitContactInformation properties
            $columnToProperty = array(
              'EMAIL'            => 'email',
              'TELEPHONE'        => 'telephone',
              'ADDRESS_Street'   => 'streetAddress',
              'ADDRESS_City'     => 'city',
              'ADDRESS_Province' => 'region',
              'POSTAL CODE'      => 'postalCode'
            );

            // set up creation of contact infomation
            $contactData = array();
            foreach($columnToProperty as $column => $property)
            {
              if (isset($self->rowStatusVars[$column]))
              {
                $contactData[$property] = $self->rowStatusVars[$column];
              }
            }

            // create contact information if none exists
            $self->createOrFetchContactInformation($actor->id, $contactData);

            // create relation between accession and donor
            $self->createRelation($self->object->id, $actor->id, QubitTerm::DONOR_ID);
          }
        }
      }
    ));

    $this->setUpExtentColumnHandling($import);
    $this->setUpProcessingNoteColumnHandling($import);

    $import->addColumnHandler('DATE OF ACQUISITION', function(&$self, $data)
    {
      if ($data)
      {
        if (isset($self->object) && is_object($self->object))
        { 
          $parsedDate = $self->parseDateLoggingErrors($data);
          if ($parsedDate) {
            $self->object->date = $parsedDate;
          }
        }
      }
    });

    $import->addColumnHandler('TYPE', function(&$self, $data)
    {
      if ($data)
      {
        $cvaToQubit = array(
          'Private records' => 'Private transfer',
          'Public records'  => 'Public transfer'
        );

        if (isset($self->object) && is_object($self->object))
        {
          $self->object->resourceTypeId = $self->translateNameToTermId(
            'transfer type',
            $data,
            $cvaToQubit,
            $self->getStatus('resourceTypes')
          );
        }
      }
    });

    $import->addColumnHandler('ACQUISITION METHOD', function(&$self, $data)
    {
      if ($data)
      {
        $cvaToQubit = array(
          'Copy Loan'          => 'Deposit', // is this correct?
          'Donation'           => 'Gift',
          'Direct Transfer'    => 'Transfer',
          'Scheduled Transfer' => 'Transfer'
        );

        if (isset($self->object) && is_object($self->object))
        {
          $self->object->acquisitionTypeId = $self->translateNameToTermId(
            'acquisition type',
            $data,
            $cvaToQubit,
            $self->getStatus('acquisitionTypes')
          );
        }
      }
    });

    $import->csv($fh, $skipRows);
  }

  public function appendColumnDataToRowVarWithColumnSpecificPrefix(
    &$import,
    $column,
    $data, 
    $rowStatusVarName, 
    $columnsAndPrefixes
  )
  {
    if ($data)
    {
      // initialize column value storage
      $import->rowStatusVars[$rowStatusVarName] = (isset($import->rowStatusVars[$rowStatusVarName]))
        ? $import->rowStatusVars[$rowStatusVarName]
        : '';

      // determine appropriate column prefix (can be blank)
      $prefix = $columnsAndPrefixes[$column];

      // append prefixed value to column value
      $import->rowStatusVars[$rowStatusVarName] = $import->appendWithLineBreakIfNeeded(
        $import->rowStatusVars[$rowStatusVarName],
        $prefix . $data
      );
    }
  }

  protected function setUpExtentColumnHandling(&$import)
  {
    // map of extent-related column names to their corresponding prefixes
    $extentColumnsAndPrefixes = array(
      'INARCHITECTURALPLAN' => "Plans (count): ",
      'INAUDIOCASSETTE'     => "Audio Cassettes (count): ",
      'INAUDIOREEL'         => "Audio Reels (count): ",
      'INCOMPACTDISC'       => "CDs (count): ",
      'INDIGITALPHOTO'      => "Digital Photos (count): ",
      'INDOCUMENTARYART'    => "Doc Art (count): ",
      'INDVD'               => "DVDs (count): ",
      'INFILMREEL'          => "Film Reels (count): ",
      'INMAP'               => "Maps (count): ",
      'INMICROFICHE'        => "Microfiche (count): ",
      'INMICROFILM'         => "Microfilm Reels (count): ",
      'INNEGATIVE'          => "Photo Negs (count): ",
      'INOTHER MATERIALS'   => "Other Materials (count): ",
      'INPHOTOGRAPHICPRINT' => "Photo Prints (count): ",
      'INSLIDE'             => "Slides (count): ",
      'INTEXTUALRECORDS'    => "Textual (m): ",
      'INVIDEOCASSETTE'     => "Video Cassettes (count): "
    );

    // store column/prefix data as we need to access it from inside a handler
    $import->setStatus('extentColumnsAndPrefixes', $extentColumnsAndPrefixes);

    // handling logic for extent columns
    $extentColumnHandler = function(&$self, $data)
    {
      csvAccessionImportTask::appendColumnDataToRowVarWithColumnSpecificPrefix(
        $self,
        $self->status['currentColumn'],
        $data,
        'receivedExtentUnits',
        $self->getStatus('extentColumnsAndPrefixes')
      );
    };

    // add handler for each extent-related column
    $import->addColumnHandlers(
      array_keys($extentColumnsAndPrefixes), 
      $extentColumnHandler
    );
  }

  protected function setUpProcessingNoteColumnHandling(&$import)
  {
    // map of processing-note-related column names to their corresponding prefixes
    $processingNoteColumnsAndPrefixes = array(
      'ACCESSION NOTE'     => "",
      'ACKNOWLEDGMENT'     => "Acknowledge Donor in Description? (Y/N): ",
      'ARCHIVIST'          => "Registered by: ",
      'CVA NUMBER'         => "CVA #: ",
      'ItemNumberTracking' => "Last Item #: ",
      'PR SERIES NUMBER'   => "PR Series #: ",
      'PRI REC NO'         => "Private Rec. #: ",
      'RECORD ID'          => "CS Record ID: ",
      'TRANSFER NUMBER'    => "RM Transfer #: ",
      'VanRims Number'     => "Classification #: ",
      'COPYRIGHT STATUS'   => "Copyright Note: ",
      'RESTRICTIONS'       => "Restrictions Note: "
    );

    // store column/prefix data as we need to access it from inside a handler
    $import->setStatus('processingNoteColumnsAndPrefixes', $processingNoteColumnsAndPrefixes);

    // handling logic for extent columns
    $processingNoteColumnHandler = function(&$self, $data)
    {
      csvAccessionImportTask::appendColumnDataToRowVarWithColumnSpecificPrefix(
        $self,
        $self->status['currentColumn'],
        $data,
        'processingNotes',
        $self->getStatus('processingNoteColumnsAndPrefixes')
      );
    };

    // add handler for each extent-related column
    $import->addColumnHandlers(
      array_keys($processingNoteColumnsAndPrefixes),
      $processingNoteColumnHandler
    );
  }
}
