<?php

/*
 * This file is part of the idlDoctrineMigrationPlugin
 * (c) Idael Software <info AT idael.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * idlDoctrineModelUpdateTask provide the symfony task model:update
 *
 * @package    idlDoctrineMigrationPlugin
 * @author     David Jeanmonod  <david AT idael.ch>
 */
class idlDoctrineModelUpdateTask extends idlDoctrineModelBaseTask {
  
  
  /**
   * @see sfTask::configure()
   */
  protected function configure() {
    parent::configure();  
    $this->namespace = 'model';
    $this->name = 'update';
    $this->briefDescription = 'Update doctrine model/form/filter, but check first that potential migration script have been generate';
    $this->detailedDescription = <<<EOF

The [idl:model update|INFO] action is going to update all your model/form/filter, according to the current
 schema.yml. If the database need to be migrate, this task is going to ask you if you want to
 processed.

When the database need to be reload, you can auto load the fixture with the option [--reload|COMMENT].
 
If there is missing migration script for the last schema change, you will be force to first 
 generate thoses scripts, with the action [model:diff|INFO]
 
EOF;

    $this->addOptions(array(
      new sfCommandOption('reload', null, sfCommandOption::PARAMETER_NONE, 'Reload fixture data', null),
      new sfCommandOption('no-confirmation', null, sfCommandOption::PARAMETER_NONE, 'Auto migrate and auto reload the database without asking', null)
    )); 
  }
  
  
  /**
   * @see sfTask::execute()
   * @param array      $arguments  An array of arguments
   * @param array      $options    An array of options
   * @return integer   0 if everything went fine, or an error code
   */
  protected function execute($arguments = array(), $options = array()) {
    
    // Load the database config
    $this->loadDBConfig();

    // Check for pending changes
    $this->initMigrationManager();
    if ( $this->isSchemaModified() ) {
      throw new sfException("The schema.yml is dirty, there is ".$this->changeNbr." modifications pending, please run [model:diff] before doing the update");
    }
    
    // Clear database if require
    if ($options['reload']){
      if ($options['no-confirmation'] || $this->askConfirmation(array(
        'The option --reload has effect to reset the database. You are going to LOSE ALL YOUR DATA, do you want to continue? (y/N)'
      ), 'QUESTION_LARGE', false)){
        $this->runTask('doctrine:drop-db', array(), array('no-confirmation'=>true));
        $this->runTask('doctrine:build-db', array(), array());
      }
      else {
        throw new Exception("Reload cancel by user");
      }
    }

    // Migrate if require
    if ($this->hasPendingDBMigration()){
      $migration = new Doctrine_Migration($this->getMigrationScriptDir());
      if ($options['reload'] || $options['no-confirmation'] || $this->askConfirmation(array(
        'The database is not up to date, do you want to migrate from version '.$migration->getCurrentVersion().' to ',
        'version '.$migration->getLatestVersion(). '? (y/N)'
      ), 'QUESTION_LARGE', false)){
        $this->logSection('migration', 'Migration start...');
        $this->runTask('sw:doctrine-migrate', array(), array());
      }
    }
    
    // regenerate code and remove the old model classes
    $this->runTask('doctrine:build-model', array(), array());
    $customGenerator = sfConfig::get('app_idl_doctrine_migration_custom_generator',array());
    $customGenerator = array_merge(array('form' => 'sfDoctrineFormGenerator','filter' => 'sfDoctrineFormFilterGenerator'), $customGenerator);
    $this->runTask('doctrine:build-forms', array(), array('generator-class' => $customGenerator['form']));
    $this->runTask('doctrine:build-filters', array(), array('generator-class' => $customGenerator['filter']));
    $this->runTask('doctrine:clean-model-files', array(), array());
      
    // Reload fixtures if request
    if ($options['reload']){
      $this->runTask('doctrine:data-load', array(), array());
    }
    
  }
  
}
