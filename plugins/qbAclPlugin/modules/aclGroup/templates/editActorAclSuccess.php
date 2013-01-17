<?php use_helper('Javascript') ?>

<h1><?php echo __('Edit %1% permissions', array('%1%' => sfConfig::get('app_ui_label_actor'))) ?></h1>

<h1 class="label"><?php echo $resource ?></h1>

<?php echo get_partial('addActorDialog', array('basicActions' => $basicActions)) ?>

<form method="post" action="<?php echo url_for(array($resource, 'module' => 'aclGroup', 'action' => 'editActorAcl')) ?>" id="editForm">

<?php foreach ($actors as $objectId => $permissions): ?>
  <div class="form-item">
<?php echo get_component('aclGroup', 'aclTable', array('object' => QubitActor::getById($objectId), 'permissions' => $permissions, 'actions' => $basicActions)) ?>
  </div>
<?php endforeach; ?>

  <div class="form-item">
    <label for="addActorLink"><?php echo __('Add permissions by %1%', array('%1%' => sfConfig::get('app_ui_label_actor'))) ?></label>
    <a id="addActorLink" href="javascript:myDialog.show()"><?php echo __('Add %1%', array('%1%' => sfConfig::get('app_ui_label_actor'))) ?></a>
  </div>

  <div class="actions section">
    <h2 class="element-invisible"><?php echo __('Actions') ?></h2>
    <div class="content">
      <ul class="clearfix links">
        <li><?php echo link_to(__('Cancel'), array($resource, 'module' => 'aclGroup', 'action' => 'indexActorAcl')) ?></li>
        <li><input class="form-submit" type="submit" value="<?php echo __('Save') ?>"/></li>
      </ul>
    </div>
  </div>

</form>
