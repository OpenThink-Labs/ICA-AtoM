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

class InformationObjectTreeViewComponent extends sfComponent
{
  public function execute($request)
  {
    $this->resource = $request->getAttribute('sf_route')->resource;

    // We don't want to support sorting when sorting by other than lft
    $this->sortable = 'none' == sfConfig::get('app_sort_treeview_informationobject') && QubitAcl::check($this->resource, 'update');

    // At this point we don't need to do any ACL check on ancestors
    $this->ancestors = $this->resource->getAncestors()->orderBy('lft');

    // Number of siblings that we are showing above and below the current node
    // It's good to keep this number small since getTreeViewSiblings can be very
    // slow (when sorting by title or identifierTitle)
    $numberOfPreviousOrNextSiblings = 4;

    // Previous siblings
    // Get an extra sibling just to know if the + button is necessary
    $this->prevSiblings = $this->resource->getTreeViewSiblings(array('limit' => $numberOfPreviousOrNextSiblings + 1, 'position' => 'previous'));
    $this->hasPrevSiblings = count($this->prevSiblings) > $numberOfPreviousOrNextSiblings;
    if ($this->hasPrevSiblings)
    {
      array_pop($this->prevSiblings);
    }

    // Reverse array
    $this->prevSiblings = array_reverse($this->prevSiblings);

    // Next siblings, same logic than above with the + button
    $this->nextSiblings = $this->resource->getTreeViewSiblings(array('limit' => 5, 'position' => 'next'));
    $this->hasNextSiblings = count($this->nextSiblings) > $numberOfPreviousOrNextSiblings;
    if ($this->hasNextSiblings)
    {
      array_pop($this->nextSiblings);
    }
  }
}
