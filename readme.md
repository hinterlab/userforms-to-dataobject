# UserForms To DataObject

## Introduction
Allow submitted userform fields to be saved into dataobject

## Requirements
* SilverStripe CMS ^4.0
* Userforms
* Multivaluefield

## Usage
Define exactly which dataobjects you can map submitted forms to

```
Internetrix\UserFormsToDataObject\UF2DOConfig:
  allowed_data_object_classes:
    <Namespace/DataObjectClassName>: Title
```

Disallow any fields by

```
Internetrix\UserFormsToDataObject\UF2DOConfig:
  data_mapping_disallow_db_fields:
    - 'ClassName'
    - 'Created'
    - 'LastEdited'
    - 'ID'
```




