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
 * QubitUser model
 */
class QubitUser extends BaseUser
{
  public function __toString()
  {
    return (string) $this->username;
  }

  public function __get($name)
  {
    $args = func_get_args();

    $options = array();
    if (1 < count($args))
    {
      $options = $args[1];
    }

    switch ($name)
    {
      // Return QubitNote objects that matches USER_ID with this resource
      // BaseUser relies in BaseObject, which looks up QubitUser.OBJECT_ID column
      case 'notes':

      if (!isset($this->refFkValues['notes']))
      {
        if (!isset($this->id))
        {
          $this->refFkValues['notes'] = QubitQuery::create();
        }
        else
        {
          $this->refFkValues['notes'] = self::getnotesById($this->id, array('self' => $this) + $options);
        }
      }

      return $this->refFkValues['notes'];
    }

    return call_user_func_array(array($this, 'BaseUser::__get'), $args);
  }

  public function save($connection = null)
  {
    parent::save($connection);

    foreach ($this->aclUserGroups as $aclUserGroup)
    {
      $aclUserGroup->user = $this;

      try
      {
        $aclUserGroup->save();
      }
      catch (PropelException $e)
      {
      }
    }

    foreach ($this->aclPermissions as $aclPermission)
    {
      $aclPermission->user = $this;

      try
      {
        $aclPermission->save();
      }
      catch (PropelException $e)
      {
      }
    }

    return $this;
  }

  public function setPassword($password)
  {
    $salt = md5(rand(100000, 999999).$this->getEmail());
    $this->setSalt($salt);
    $this->setSha1Password(sha1($salt.$password));
  }

  public function getAclGroups()
  {
    // Add all users to 'authenticated' group
    $authenticatedGroup = QubitAclGroup::getById(QubitAclGroup::AUTHENTICATED_ID);

    $groups = array($authenticatedGroup);
    foreach ($this->getAclUserGroups() as $userGroup)
    {
      $groups[] = $userGroup->getGroup();
    }

    return $groups;
  }

  public function getUserCredentials()
  {
    return $this->getAclGroups();
  }

  public static function checkCredentials($username, $password, &$error)
  {
    $validCreds = false;
    $error = null;

    // anonymous is not a real user
    if ($username == 'anonymous')
    {
      $error = 'invalid username';

      return null;
    }

    $criteria = new Criteria;
    $criteria->add(QubitUser::EMAIL, $username);
    $user = QubitUser::getOne($criteria);

    // user account exists?
    if ($user !== null)
    {
      // Stop if user is not active
      if (!$user->active)
      {
        $error = 'inactive user';

        return null;
      }

      // password is OK?
      if (sha1($user->getSalt().$password) == $user->getSha1Password())
      {
        $validCreds = true;
      }
      else
      {
        $error = 'invalid password';
      }
    }
    else
    {
      $error = 'invalid username';
    }

    return ($validCreds) ? $user : null;
  }

  /**
   * Check if user belongs to *any* of the checkGroup(s) listed
   *
   * @param mixed $groups - integer value for group id, or array of group ids
   * @return boolean
   */
  public function hasGroup($checkGroups)
  {
    $hasGroup = false;

    // Cast $checkGroups as an array
    if (!is_array($checkGroups))
    {
      $checkGroups = array($checkGroups);
    }

    // A user is always part of the authenticated group
    if (in_array(QubitAclGroup::AUTHENTICATED_ID, $checkGroups))
    {
      return true;
    }

    $criteria = new Criteria;
    $criteria->add(QubitAclUserGroup::USER_ID, $this->id);

    if (0 < count($userGroups = QubitAclUserGroup::get($criteria)))
    {
      foreach ($userGroups as $userGroup)
      {
        if (in_array(intval($userGroup->groupId), $checkGroups))
        {
          $hasGroup = true;
          break;
        }
      }
    }

    return $hasGroup;
  }

  /**
   * Get system admin
   *
   * We are assuming the first admin user is the system admin
   *
   * @return QubitUser
   */
  public static function getSystemAdmin()
  {
    foreach (self::getAll() as $user)
    {
      if ($user->hasGroup(QubitAclGroup::ADMINISTRATOR_ID))
      {
        return $user;
      }
    }
  }

  /**
   * Get an array of QubitRepository objects where the current user has been
   * added explicit access via its own user of any of its groups
   *
   * @return QubitUser
   */
  public function getRepositories()
  {
    // Get user's groups
    $userGroups = array();
    if (0 < count($aclUserGroups = $this->aclUserGroups))
    {
      foreach ($aclUserGroups as $aclUserGroup)
      {
        $userGroups[] = $aclUserGroup->groupId;
      }
    }
    else
    {
      // User is *always* part of authenticated group
      $userGroups = array(QubitAclGroup::AUTHENTICATED_ID);
    }

    // Get access control permissions
    $criteria = new Criteria;
    $criteria->addJoin(QubitAclPermission::OBJECT_ID, QubitObject::ID, Criteria::LEFT_JOIN);
    $c1 = $criteria->getNewCriterion(QubitAclPermission::USER_ID, $this->id);

    // Add group criteria
    if (1 == count($userGroups))
    {
      $c2 = $criteria->getNewCriterion(QubitAclPermission::GROUP_ID, $userGroups[0]);
    }
    else
    {
      $c2 = $criteria->getNewCriterion(QubitAclPermission::GROUP_ID, $userGroups, Criteria::IN);
    }
    $c1->addOr($c2);

    // Add information object criteria
    $c3 = $criteria->getNewCriterion(QubitObject::CLASS_NAME, 'QubitInformationObject');
    $c4 = $criteria->getNewCriterion(QubitAclPermission::OBJECT_ID, null, Criteria::ISNULL);
    $c3->addOr($c4);
    $c1->addAnd($c3);
    $criteria->add($c1);

    // Sort
    $criteria->addAscendingOrderByColumn(QubitAclPermission::CONSTANTS);
    $criteria->addAscendingOrderByColumn(QubitAclPermission::OBJECT_ID);
    $criteria->addAscendingOrderByColumn(QubitAclPermission::USER_ID);
    $criteria->addAscendingOrderByColumn(QubitAclPermission::GROUP_ID);

    // Build ACL
    $repositories = array();
    if (0 < count($permissions = QubitAclPermission::get($criteria)))
    {
      foreach ($permissions as $item)
      {
        if (null !== $constant = $item->getConstants(array('name' => 'repository')))
        {
          if (!isset($repositories[$constant]))
          {
            $repositories[$constant] = QubitRepository::getBySlug($constant);
          }
        }
      }
    }

    return $repositories;
  }
}
