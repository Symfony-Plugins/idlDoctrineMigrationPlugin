<?php

/*
 * This file is part of the idlDoctrineMigrationPlugin
 * (c) Idael Software <info AT idael.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * idlDoctrineModelDiffTask provide the symfony task model:diff
 *
 * @package    idlDoctrineMigrationPlugin
 * @author     David Jeanmonod  <david AT idael.ch>
 */
class idlDoctrineModelDiffTask extends idlDoctrineModelBaseTask {

  
  /**
   * @see sfTask::configure()
   */
  protected function configure() {
    parent::configure();
    $this->namespace = 'model';
    $this->name = 'diff';
    $this->briefDescription = 'Generate migration script if require and backup schema.yml for next model:diff usage';
    $this->detailedDescription = <<<EOF
The [model:diff|INFO] action is checking for schema.yml change. If change are detected, the migration scrit are 
 going to be generated in the [lib/migration/doctrine|INFO] directory
EOF;
  }

  
  /**
   * @see sfTask::execute()
   * @param array      $arguments  An array of arguments
   * @param array      $options    An array of options
   * @return integer   0 if everything went fine, or an error code
   */
  protected function execute($arguments = array(), $options = array()) {
    
    // Load the database config
    $dbNames = $this->loadDBConfig();
       
    // Check for diff
    $this->logSection('migration', 'Check for schema update');
    $this->initMigrationManager();
    if ( ! $this->isSchemaModified() ) {
      $this->logBlock('No diff found, nothing to migrate', 'QUESTION_LARGE');
      return 0;
    }

    // Generate migration scripts
    $this->logSection('migration', $this->changeNbr.' diff found, generation of a new migration script');
    $this->migrationManager->generateMigrationClasses();
    $this->saveNewYmlReferenceFile();
    $this->logBlock(array('New migration script generated in :', '  '.$this->getMigrationScriptDir(), 'please review them before call idl:model update' ), 'QUESTION_LARGE');
    return 0;
    
  }
  
}
