<?php

/*
 * This file is part of the idlDoctrineMigrationPlugin
 * (c) Idael Software <info AT idael.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * idlDoctrineModelBaseTask provide common functionnality for symfony task 
 * model:diff and model:update
 *
 * @package    idlDoctrineMigrationPlugin
 * @author     David Jeanmonod  <david AT idael.ch>
 */
abstract class idlDoctrineModelBaseTask extends sfDoctrineBaseTask {
  
  protected $migrationManager = null;
  protected $potentialNewRefFile = null;
  protected $changeNbr = null;
  
  
  /**
   * @see sfTask::configure()
   */
  protected function configure() {  
    $this->addOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev');
  }
  
  
  /**
   * Load the database config according to environement task settings
   */  
  protected function loadDBConfig(){
    $dbNames =  $this->getDoctrineDatabases(new sfDatabaseManager($this->configuration));
    return $dbNames;
  }
  
  
  /**
   * Return the path where are saved the migration script
   * @return string   
   */
  protected function getMigrationScriptDir(){
    $dir =  sfConfig::get('sf_lib_dir').'/migration/doctrine';
    if (!is_dir($dir)){
      $this->getFilesystem()->mkdirs($dir);
    }
    return $dir;
  }
  
  
  /**
   * Return the path where are saved the schema history
   * @return string   
   */
  protected function getMigrationHistoryDir(){
    $dir = sfConfig::get('sf_data_dir').'/migration/history';
    if (!is_dir($dir)){
      $this->getFilesystem()->mkdirs($dir);
    }
    return $dir;
  }
  
  
  /**
   * Return the path to an empty model for the first migration
   * @return string   
   */
  protected function getEmptyModelDir(){
    return realpath(dirname(__FILE__).'/../../data/empty_model');
  }
  
  
  /**
   * Create a migration manager, and process a diff between the current schema.yml and the one from the history
   */
  protected function initMigrationManager() {
    
    // Prepare current and previous schema
    $doctrineConfig = $this->getCliConfig();
    $tempSchemaFile = $this->prepareSchemaFile($doctrineConfig['yaml_schema_path']);
    $previousSchemaFile = $this->getLastYmlRefFile();
    
    // If there is no previous schema file, this means that the there is currently no ref file, so we have to init 
    //  the first migration with an empty model
    if ($previousSchemaFile == null){
      $previousSchemaFile = $this->getEmptyModelDir();
    }
    
    // Generate schema change
    $this->migrationManager = new Doctrine_Migration_Diff($previousSchemaFile, $tempSchemaFile, $this->getMigrationScriptDir());
    spl_autoload_register(array('Doctrine_Core', 'modelsAutoload'));
    $changes = $this->migrationManager->generateChanges();
    $this->changeNbr = count($changes, true) - count($changes);
    
    // If there is change, keep the ref file for potential save 
    if ($this->changeNbr > 0){
      $this->potentialNewRefFile = $tempSchemaFile;
    }  
  }
  
  
  /**
   * Save the reference file in the history directory with current timestamp as name
   */
  protected function saveNewYmlReferenceFile() {
    $newSchemaFile = $this->getMigrationHistoryDir().DIRECTORY_SEPARATOR.time().'.yml';
    $this->logSection('migration', 'Backup the current schema.yml for next diff usage');
    $this->getFilesystem()->copy($this->potentialNewRefFile, $newSchemaFile);
  }
  
  
  /**
   * Return if the current schema have database modifications compare to the one from history 
   * @return boolean   
   */
  protected function isSchemaModified(){
    if ($this->changeNbr === null)
      throw new Exception("You must call the initMigrationManager() method before calling the isSchemaModified()");
    return $this->changeNbr > 0;
  }
  

  
  /**
   * Return the last reference file or null, if there is no file
   * @return string|null    Path to the last yml file
   */
  protected function getLastYmlRefFile(){
    $files = sfFinder::type('file')->name('*.yml')->relative()->in($this->getMigrationHistoryDir());
    $biggestTS = 0;
    $lastFile = null;
    foreach ($files as $file){
      preg_match('/^(\d+).*/', $file, $match);
      $ts = (int) $match[0];
      if ($ts > $biggestTS){
        $lastFile = $file;
        $biggestTS = $ts;
      }
    }
    return $lastFile==null ? null : $this->getMigrationHistoryDir().DIRECTORY_SEPARATOR.$lastFile;
  }

  /**
   * Return if the database is not up to date
   * @return boolean    
   */
  protected function hasPendingDBMigration(){    
    $migration = new Doctrine_Migration($this->getMigrationScriptDir());
    $from = $migration->getCurrentVersion();
    $to = $migration->getLatestVersion();
    return $from < $to;
  }
}
