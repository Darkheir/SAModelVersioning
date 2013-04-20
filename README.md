SAModelVersioning
===========

SAModelVersioning is a Yii behavior that provides versioning capabilities to any ActiveRecord object.

##Description 

Using this behavior, you can:

* Revert an object to previous versions easily
* Track and browse history of the modifications of an object

##Quick example

```php
$book = new Book;
$book->title = 'The correctio';
$book->author = 'Jonathan Franzen';
$book->save();
echo $book->version; //1
$book->title = 'The corrections';
$book->save();
echo $book->version; //2

$book->toVersion(1);
echo $book->title; //'The correctio'
// saving a previous version creates a new one
$book->save();
echo $book->version; // 3
```

##Requirements 

* Yii 1.1 or above
* PHP 5.3
* a Mysql database
* A primary key named `id` in the model table (may change in the next version if some people need the ability to customize it)

##Installation

 The first thing to do is extract the `SAModelVersioning.php` file in your `extension` folder.

###Modifying the active record table

 I tried to change as little as I could in the original table. This is why the only stuff you'll need to change is add a `version` field that will store the current version of the object.

To create it you can run the following sql line:

```sql
 ALTER TABLE  `yourTable` ADD  `version` INT NOT NULL
```

###Creating the version tables
 
 For each model that will be versioned you'll need to create a version table. 
 By default the version table has to be named `{model_table_name}_version`.
 For example if the table is named `news` then the version table will be named `news_version`.

 You can specify another name for the table when you declare the behavior using the param `VersionTable`. See above for more informations.

**/!\  This table need to use the MyISAM engine.**

 This table will have all the field of the base model table plus a few:

 * `version` that will store the version number of the datas stored.
 * `version_comment` that can store a comment for this version
 * `created_by` that can store the id of the person who saved this version for example
 * `created_time` that will store at what time this version has been created

If in your model one of the field already has one of the names listed above, you'll be able to change the name ofthose fields by specifying it when declaring the behavior (see below).

Another thing to do is create a primary key that'll be defined by id AND version

So let's say we have the following table model:

```sql
CREATE TABLE `news` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
)
```

Then you'll have to create the following table:

```sql
CREATE TABLE `news_version` (
  `id` int(11) NOT NULL,
  `version` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  `version_comment` varchar(255) NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `created_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `version`)
);
```
**/!\ It's important to define the primary key starting with `id` and then `version`**

## Configuration: Declaring the behavior

When the database is ready to go using this extension is pretty simple: You just have to declare the behavior in your active record class:

```php
public function behaviors(){
    return array(
      'modelVersioning' => array(
          'class' => 'application.extensions.SAModelVersioning',
            )
    );
  }
```

As I said above there are few params that you can use to customize the behavior:

* `versionTable` let you choose the name of the version table. Default to `{model_table_name}_version`.
* `createdByField` let you change the name of the db field representing who created this version. Default to `created_by`.
* `createdAtField` let you change the name of the db field representing at what time this version was created. Default to `created_time`.
* `versionCommentField` let you change the name of the db field representing the comment for this version. Default to `version_comment`.
* `versionField` let you change the name of the db field that will store the version number. Default to `version`
* `removeVersioningOnDelete` let you decide if the versioned entries must be deleted when the original model is deleted. Default to `true`.

Here is an example of the behavior declaration with the params:

```php
'modelVersioning' => array(
  'class' => 'application.extensions.SAModelVersioning',
  'versionTable' => 'version_table_for_this_model', //default to `{primary_table}_version`
  'versionField' => 'yourNewField', //default to `version`
  'createdByField' => 'yourNewField', //default to `created_by`
  'createdAtField' => 'yourNewField', //default to `created_time`
  'versionCommentField' => 'yourNewField', //default to `version_comment`
  'removeVersioningOnDelete' => false //default to `true`
)
```
##Usage

### Basic Usage

For a basic usage, the versioning is done automatically when you call the `save()` method from your model.

```php
$book = new Book;
$book->title = 'The correctio';
$book->author = 'Jonathan Franzen';
$book->save(); // The new version is stored in the version table
echo $book->version; //1
$book->title = 'The corrections';
$book->save(); // The new version is stored in the version table
echo $book->version; //2
```
###Adding details
When storing a new version, it could be usefull to add some details about this version :
* At what time this version was created
* Who created this version
* Why this version was created 

This behavior let you do that :
```php
$book = new Book;
$book->title = 'The corrections';
$book->author = 'Jonathan Franzen';
$book->versionCreatedBy = "John Doe";
$book->versionComment = "Creation of the book in the db";
$book->save(); // The date time is automatically added when saving the model
```

###Playing with the version history (revert, compare, ...)

This extension provides some methods to interract with the version history of your models.
In the following example you'll find how to use them.
```php
$book = Book::model()->findByPk(1);
$book->isLastVersion();//Return true

//Revert the model to version 1
$book->toVersion(1);
$book->isLastVersion();//Return false
$book->save(); //By saving the model a new version will be created
$book->isLastVersion();//Return true

// To know the last version number
echo $book->getLastVersionNumber();

//Assign an old version to the variable
$oldVersionBook = $book->getOneVersion(2);
echo $oldVersionBook->version;//2

//Get the 3 last versions
$bookLastVersions = $book->getLastVersions(3);//getLastVersions() will return only the last version
foreach($bookLastVersions as $key=>$book)  {
  echo $book->version; //Will display something like 8, 7, 6 (if 8 is the last version)
}

//Get all the versions
$bookAllVersions = $book->getAllVersions();
foreach($bookAllVersions as $key=>$book)  {
  echo $book->version; //Will display something like 1, 2, 3, 4, 5 (if 5 is the last version)
}

//Compare 2 versions of an active record
$differences = $book->compareVersions(1, 3);
var_dump($differences);
// If the only difference is in the title it will print something like :
array(
  'title'=> array(
    '1' => 'The correctio',
    '3' => 'The corrections',
  )
)

// Compare the actual model to an old version:
$differences = $book->CompareTo(1);
var_dump($differences);
//If the only difference is in the title it will print something like :
array(
  'title'=> array(
    'actual' => 'The corrections',
    '1' => 'The correctio',
  )
)

//Remove the versioning for this model
$book->deleteVersioning();
```

##List of available methods and attributes:

### The public methods
* ` getVersion()` : return the version of the current model
* `getVersionTable()` : return the name of the version table for this active record type
* `isLastVersion()` : return if the version of this model is the last one
* `getAllVersions()` : return all the versions of the model
* `getLastVersionNumber()` : return the number of the last version
* `getLastVersions($number = 1)` : return the n last versions
* `getOneVersion($versionNumber)` : return the given version
* `toVersion($versionNumber)` : revert the model to the given version
* `deleteVersioning()` : delete all the version of this model
* `compareVersions($version1, $version2)` : compare 2 versions
* `compareTo($versionNumber)` : compare the actual model to the given version

### The public attributes
* `$createdByField` : (String) Name of the field holding who created this version
* `$createdAtField` : (String) Name of the field holding when this version was created 
* `$versionCommentField` : (String) Name of the field holding the version comment
* `$versionField` : (String) Name of the field holding the version number
* `$removeVersioningOnDelete` : (Boolean) If we need to remove the versioning when the model is deleted

Of course because of yii you can also call :

* `$this->version` to get the version number of the current model instead of calling `$this->getVersion()`
* `$this->versionTable` to get the name of the version table instead of calling `$this->getVersionTable()`

## Please Note

Don't hesitate to ask for help or tell me any issues you could meet while using the plugin!
If you see some typo errors in this extension page please tell me, English is not my mother tongue so there might have plenty of them.

Feel free to provide some ideas of improvment and if you provide some pull request on github I'll most definitely examinate your code and add it to the widget if I think it adds something!

## Links

* [The Github page](https://github.com/Darkheir/SAModelVersioning)

##Updates


