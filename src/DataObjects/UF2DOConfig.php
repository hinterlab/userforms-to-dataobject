<?php

namespace Internetrix\UserFormsToDataObject;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\HasManyList;
use SilverStripe\UserForms\Model\Submission\SubmittedFileField;
use SilverStripe\UserForms\Model\UserDefinedForm;
use Symbiote\MultiValueField\Fields\KeyValueField;

/**
 * User Form to Data Object Config
 */
class UF2DOConfig extends DataObject {

    private static $table_name = 'UF2DOConfig';
	
	private static $singular_name = 'Configuration';
	
	private static $plural_name = 'Configurations';
	
	private static $db = array(
		'Active' 			=> 'Boolean',
		'ActionType' 		=> "Enum('AddNew, UpdateOnly', 'AddNew')",
		'SessionIDName' 	=> "Varchar(64)",		//for 'UpdateOnly' option
		'DestClass' 		=> 'Varchar(64)',		// save into destination dataobject class name
		'DataMapping'		=> 'MultiValueField'	// submitted form fields into dataobject.
	);
	
	private static $defaults = array(
		'Active' 			=> true
	);
	
	private static $summary_fields = array(
		'Active.Nice'		=> 'Active',
		'getSourceName'		=> 'Name of Data Record',
		'getFormFieldNames'	=> 'Assigned Form Fields'
	);
	
	private static $has_one = array(
		'Parent' => UserDefinedForm::class
	);
	
	public function getSourceName(){
		if($this->DestClass && class_exists($this->DestClass)){
			$niceName = singleton($this->DestClass)->singular_name();
			if($niceName){
				return $niceName;
			}else{
				return $this->DestClass;
			}
		}
		
		return '';
	}
	
	public function getUserFormFields(){
		$userForm = $this->Parent();
		
		if($userForm && $userForm->ID){
			return $userForm->Fields();
		}
		
		return false;
	}
	
	/**
	 * get field names for summary field.
	 * 
	 * @return HTML string
	 */
	public function getFormFieldNames(){
		
		$userFormFields = $this->getUserFormFields();
		
		if($userFormFields && $userFormFields->Count()){
			$assignedFields = $this->getAssignedFormFields();
			
			//only return assigned editable form fields IDs
			if($assignedFields && count($assignedFields)){
				$fieldsNames = $userFormFields->map('Name', 'Title')->toArray();
			
				$htmlValue = "<ul>";
				foreach ($fieldsNames as $name => $title){
					if(in_array($name, $assignedFields)){
						$htmlValue .= "<li>{$title}</li>";
					}
				}
				$htmlValue .= "</ul>";

				$html = DBField::create_field('HTMLText','FormFieldNames');
				$html->setValue($htmlValue);
				
				return $html;
			}
		}
		
		return '';
	}
	
	
	/**
	 * get field names for summary field.
	 * 
	 * @return array | false
	 */
	public function getAssignedFormFields(){
		$mappingDataArray = $this->dbObject('DataMapping')->getValue();

		if(is_array($mappingDataArray) && count($mappingDataArray)){
			$formFieldsNames = array_keys($mappingDataArray);
			return array_combine($formFieldsNames, $formFieldsNames);
		}
		
		return false;
	}
	
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName('ParentID');
		$fields->removeByName('DataMapping');
		$fields->removeByName('ActionType');
		$fields->removeByName('SessionIDName');
		
		$allowedSourceClasses = Config::inst()->get(UF2DOConfig::class, 'allowed_data_object_classes');
        $allowedSourceClasses = $allowedSourceClasses ? $allowedSourceClasses : [];
		
		$fields->addFieldsToTab('Root.Main', [
			DropdownField::create('DestClass', 'Destination data object class for saving', $allowedSourceClasses)
				->setEmptyString('Select destination class ...')
		]);
		
		if($this->ID && $this->DestClass){
			$userFormFields = $this->getUserFormFields();
			
			$existingFields = $userFormFields->map('Name', 'Title')->toArray();
			
			if($existingFields && count($existingFields) && $this->DestClass){
				$fields->addFieldsToTab('Root.Main', [
					KeyValueField::create("DataMapping", 'Data Mapping', $existingFields, $this->getDBFieldsForDataMapping($this->DestClass)),
					DropdownField::create('ActionType', 'Action Type', $this->dbObject('ActionType')->enumValues()),
					TextField::create('SessionIDName', 'Session name for getting existing record by ID')
						->setRightTitle('Get record ID from PHP Session')
						->displayIf("ActionType")->isEqualTo("UpdateOnly")->end()
				]);
			}
		}else{
			//show message
			$fields->addFieldsToTab('Root.Main', [
				LiteralField::create('ConfigMSG', "<span class=\"message good\">Please select a source data object class and save this configuration.</span>")
			]);
		}
		
		return $fields;
	}
	
	
	public function getDBFieldsForDataMapping($className){
		//get DB fields names for selected class
		//TODO should support has_one like File and Image for SubmittedFileField
		$dbFieldsArray = Config::inst()->get($className, 'db');
		$hasOneFieldsArray = Config::inst()->get($className, 'has_one');
		
		$disallowDBFields = $this->stat('data_mapping_disallow_db_fields');
		if($disallowDBFields && count($disallowDBFields)){
			foreach ($disallowDBFields as $fieldname){
				unset($dbFieldsArray[$fieldname]);
			}
			
			//check has_one relationship as well
			if(count($hasOneFieldsArray)){
				foreach ($hasOneFieldsArray as $hasOneDBName => $belongsToClassName){
					if( ! in_array($hasOneDBName, $disallowDBFields)){
						$hasOneDBName = $hasOneDBName . 'ID';
						$dbFieldsArray[$hasOneDBName] = $hasOneDBName;
					}
				}
			}
		}
		
		//process array. e.g. array( 'Title' => 'Title' )
		$keys = array_keys($dbFieldsArray);
		$dbFieldsNames = array_combine($keys, $keys);
		
		return $dbFieldsNames;
	}
	

	/**
	 * save submitted form values into destination data object.
	 *
	 * @param HasManyList of SubmittedFormField
	 */
	public function saveSubmittedFormValuesIntoDataObject(HasManyList $submittedFormFields){
		
		$mappingDataArray 		= $this->dbObject('DataMapping')->getValue();
		$dbFieldsArray 			= Config::inst()->get($this->DestClass, 'db');
		$submittedFFsToBeSaved	= [];

		//process mapping data
		foreach ($submittedFormFields as $submittedFFDO){
		    $name = $submittedFFDO->getField('Name');

			//parsed EditableFormField ID and this ID exists in DataMapping.
			if(key_exists($name, $mappingDataArray)){
				
				$destDBFieldName = $mappingDataArray[$name];
				
				if($submittedFFDO->ClassName == 'SubmittedFileField'){
					$submittedFileDO = SubmittedFileField::get()->byID($submittedFFDO->ID);
					
					if($submittedFileDO->UploadedFileID){
						$thisValue = $submittedFileDO->UploadedFileID;
					}
				}else{
					$thisValue = $submittedFFDO->Value;
					
					if(isset($dbFieldsArray[$destDBFieldName]) && strcasecmp($dbFieldsArray[$destDBFieldName], 'boolean') == 0){
						//process data if DB field is Boolean
						if(strcasecmp($submittedFFDO->Value, 'no') == 0){
							//no -> (Boolean) false
							$thisValue = false;
						}elseif (strcasecmp($submittedFFDO->Value, 'yes') == 0){
							//no -> (Boolean) false
							$thisValue = true;
						}else{
							//default
							$thisValue = false;
						}
					}
				}
				
				$submittedFFsToBeSaved[$destDBFieldName] = $thisValue;
			}
		}

		//create requested data object class and save submitted form fields data into the new data object.
		if( ! empty($submittedFFsToBeSaved)){
			$className = $this->DestClass;
			
			if($this->ActionType == 'UpdateOnly' && $this->SessionIDName){
				$recordID = Controller::curr()->getRequest()->getSession()->get($this->SessionIDName);
				
				if($recordID){
					$dataobject = $className::get()->byID($recordID);
					
					if($dataobject && $dataobject->ID){
						$dataobject
							->update($submittedFFsToBeSaved)
							->write();
					}
				}
			}else{
				$dataobject = $className::create();
				$dataobject
					->update($submittedFFsToBeSaved)
					->write();
			}
		}
	}

}