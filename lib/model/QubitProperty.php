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
 * Extended methods for Property object model
 *
 * @package Qubit
 * @subpackage model
 * @author Jack Bates <jack@artefactual.com>
 * @author Peter Van Garderen <peter@artefactual.com>
 * @author David Juhasz <david@artefactual.com>
 * @version $Id: QubitProperty.php 12129 2012-08-17 17:19:59Z david $
 */
class QubitProperty extends BaseProperty
{
  public $indexOnSave = true;

  public function __toString()
  {
    $string = $this->value;
    if (!isset($string))
    {
      $string = $this->getValue(array('sourceCulture' => true));
    }

    return (string) $string;
  }

  public function save($connection = null)
  {
    // TODO: $cleanObject = $this->object->clean;
    $cleanObjectId = $this->__get('objectId', array('clean' => true));

    parent::save($connection);

    if ($this->indexOnSave)
    {
      if ($this->objectId != $cleanObjectId && null !== QubitInformationObject::getById($cleanObjectId))
      {
        QubitSearch::updateInformationObject(QubitInformationObject::getById($cleanObjectId));
      }

      if ($this->object instanceof QubitInformationObject)
      {
        QubitSearch::updateInformationObject($this->object);
      }
    }

    return $this;
  }

  /**
   * Flag whether to update the search index when saving this object
   *
   * @param boolean $bool flag value
   * @return QubitProperty self-reference
   */
  public function setIndexOnSave($bool)
  {
    if ($bool)
    {
      $this->indexOnSave = true;
    }
    else
    {
      $this->indexOnSave = false;
    }

    return $this;
  }

  public function delete($connection = null)
  {
    parent::delete($connection);

    if ($this->getObject() instanceof QubitInformationObject)
    {
      QubitSearch::updateInformationObject($this->getObject());
    }
  }

  /**
   * Get source culture text for "value" column for this property to aid in
   * translation on the front-end.
   *
   * @param string $sfUserCulture current culture selected by user
   * @return string source culture value
   */
  public function getSourceTextForTranslation($sfUserCulture)
  {
    if (strlen($sourceCultureValue = $this->getValue(array('sourceCulture' => 'true'))) > 0 && $sfUserCulture != $this->getSourceCulture())
    {
      return $sourceCultureValue;
    }

    return null;
  }

  /**
   * Get a unique property associated with object identified by $objectId
   *
   * @param integer $objectId foreign key to related object
   * @param string $name name of property
   * @param array $options optional parameter array
   * @return QubitProperty matching property (if any)
   */
  public static function getOneByObjectIdAndName($objectId, $name, $options = array())
  {
    $criteria = new Criteria;
    $criteria->add(QubitProperty::OBJECT_ID, $objectId);
    $criteria->add(QubitProperty::NAME, $name);

    if (isset($options['scope']))
    {
      $criteria->add(QubitProperty::SCOPE, $options['scope']);
    }

    return QubitProperty::getOne($criteria);
  }

  /**
   * Add property after verifying that there isn't already one with an identical
   * object_id, name, and (optionally) scope.
   *
   * @param integer $objectId related object foreign key
   * @param string  $name name of property
   * @param string  $value value to set for property
   * @param array   $options optional parameters
   * @return QubitProperty this property object
   */
  public static function addUnique($objectId, $name, $value, $options = array())
  {
    // Only add if an existing property does not exist
    if (!QubitProperty::isExistent($objectId, $name, $value, $options))
    {
      $property = new QubitProperty;
      $property->setObjectId($objectId);
      $property->setName($name);
      $property->setValue($value, $options);

      if (isset($options['scope']))
      {
        $property->setScope($options['scope']);
      }

      $property->save();

      return $property;
    }

    return null;
  }

  /**
   * Determine if a property matching passed values already exists.
   *
   * @param integer $objectId foreign key to QubitObject::ID
   * @param string $name  name of property
   * @param string $value value of property
   * @param string $options array of optional parameters
   * @return boolean true if QubitProperty exists
   */
  public static function isExistent($objectId, $name, $value, $options = array())
  {
    $propertyExists = false;

    $criteria = new Criteria;
    $criteria->addJoin(QubitProperty::ID, QubitPropertyI18n::ID);
    $criteria->add(QubitProperty::OBJECT_ID, $objectId);
    $criteria->add(QubitProperty::NAME, $name);
    $criteria->add(QubitPropertyI18n::VALUE, $value);

    if (isset($options['culture']))
    {
      $criteria->add(QubitPropertyI18n::CULTURE, $options['culture']);
    }
    else if (isset($options['sourceCulture']))
    {
      $criteria->add(QubitPropertyI18n::CULTURE, QubitProperty::SOURCE_CULTURE.' = '.QubitPropertyI18n::CULTURE, Criteria::CUSTOM);
    }
    else
    {
      $criteria->add(QubitPropertyI18n::CULTURE, sfPropel::getDefaultCulture());
    }

    if (isset($options['scope']))
    {
      $criteria->add(QubitProperty::SCOPE, $options['scope']);
    }

    // See if search returns a hit.
    if (($property = QubitProperty::getOne($criteria)) !== null)
    {
      $propertyExists = true;
    }

    return $propertyExists;
  }
}