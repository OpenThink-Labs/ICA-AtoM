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
 * This class is used to provide methods that supplement the core Qubit information object with behaviour or
 * presentation features that are specific to the Canadian Rules for Archival Description (RAD) standard
 *
 * @package    Qubit
 * @author     Peter Van Garderen <peter@artefactual.com>
 * @version    svn:$Id: sfRadPlugin.class.php 12222 2012-08-27 20:13:21Z david $
 */

class sfRadPlugin implements ArrayAccess
{
  protected
    $resource,
    $property;

  public function __construct(QubitInformationObject $resource)
  {
    $this->resource = $resource;
  }

  public function __toString()
  {
    $string = array();

    $levelOfDescriptionAndIdentifier = array();

    if (isset($this->resource->levelOfDescription))
    {
      $levelOfDescriptionAndIdentifier[] = $this->resource->levelOfDescription->__toString();
    }

    if (isset($this->resource->identifier))
    {
      $levelOfDescriptionAndIdentifier[] = $this->resource->identifier;
    }

    if (0 < count($levelOfDescriptionAndIdentifier))
    {
      $string[] = implode($levelOfDescriptionAndIdentifier, ' ');
    }

    $titleAndPublicationStatus = array();

    if (0 < strlen($title = $this->resource->__toString()))
    {
      $titleAndPublicationStatus[] = $title;
    }

    $publicationStatus = $this->resource->getPublicationStatus();
    if (isset($publicationStatus) && QubitTerm::PUBLICATION_STATUS_DRAFT_ID == $publicationStatus->statusId)
    {
      $titleAndPublicationStatus[] = "({$publicationStatus->status->__toString()})";
    }

    if (0 < count($titleAndPublicationStatus))
    {
      $string[] = implode($titleAndPublicationStatus, ' ');
    }

    return implode(' - ', $string);
  }

  public function __get($name)
  {
    switch ($name)
    {
      case 'editionStatementOfResponsibility':
      case 'issuingJurisdictionAndDenomination':
      case 'noteOnPublishersSeries':
      case 'numberingWithinPublishersSeries':
      case 'otherTitleInformation':
      case 'otherTitleInformationOfPublishersSeries':
      case 'parallelTitleOfPublishersSeries':
      case 'standardNumber':
      case 'statementOfCoordinates':
      case 'statementOfProjection':
      case 'statementOfResponsibilityRelatingToPublishersSeries':
      case 'statementOfScaleArchitectural':
      case 'statementOfScaleCartographic':
      case 'titleStatementOfResponsibility':
      case 'titleProperOfPublishersSeries':

        return $this->property($name)->value;

      case 'referenceCode':

        return $this->resource->referenceCode;

      case 'sourceCulture':

        return $this->resource->sourceCulture;

      case 'languageNotes':

        return $this->resource->getNotesByType(array('noteTypeId' => QubitTerm::LANGUAGE_NOTE_ID))->offsetGet(0);

    }
  }

  public function __set($name, $value)
  {
    switch ($name)
    {
      case 'editionStatementOfResponsibility':
      case 'issuingJurisdictionAndDenomination':
      case 'noteOnPublishersSeries':
      case 'numberingWithinPublishersSeries':
      case 'otherTitleInformation':
      case 'otherTitleInformationOfPublishersSeries':
      case 'parallelTitleOfPublishersSeries':
      case 'standardNumber':
      case 'statementOfCoordinates':
      case 'statementOfProjection':
      case 'statementOfResponsibilityRelatingToPublishersSeries':
      case 'statementOfScaleArchitectural':
      case 'statementOfScaleCartographic':
      case 'titleProperOfPublishersSeries':
      case 'titleStatementOfResponsibility':

        $this->property($name)->value = $value;

        return $this;

      case 'languageNotes':

        // Stop if the string is empty
        if (0 == strlen($value))
        {
          break;
        }

        if (0 == count($note = $this->resource->getNotesByType(array('noteTypeId' => QubitTerm::LANGUAGE_NOTE_ID))->offsetGet(0)))
        {
          $note = new QubitNote;
          $note->typeId = QubitTerm::LANGUAGE_NOTE_ID;
          $note->userId = sfContext::getInstance()->user->getAttribute('user_id');

          $this->resource->notes[] = $note;
        }

        $note->content = $value;

        return $this;

    }
  }

  protected function property($name)
  {
    if (!isset($this->property[$name]))
    {
      $criteria = new Criteria;
      $this->resource->addPropertysCriteria($criteria);
      $criteria->add(QubitProperty::NAME, $name);

      if (1 == count($query = QubitProperty::get($criteria)))
      {
        $this->property[$name] = $query[0];
      }
      else
      {
        $this->property[$name] = new QubitProperty;
        $this->property[$name]->name = $name;

        $this->resource->propertys[] = $this->property[$name];
      }
    }

    return $this->property[$name];
  }

  public function offsetExists($offset)
  {
    $args = func_get_args();

    return call_user_func_array(array($this, '__isset'), $args);
  }

  public function offsetGet($offset)
  {
    $args = func_get_args();

    return call_user_func_array(array($this, '__get'), $args);
  }

  public function offsetSet($offset, $value)
  {
    $args = func_get_args();

    return call_user_func_array(array($this, '__set'), $args);
  }

  public function offsetUnset($offset)
  {
    $args = func_get_args();

    return call_user_func_array(array($this, '__unset'), $args);
  }
}
