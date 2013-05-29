<?php
/**
 * 
 */
class SAModelVersioning extends CActiveRecordBehavior
{
	
	/**
	 * Params that can be setted when declaring the
	 * behavior.
	 * There's an additional param that can be used to 
	 * specify the version table name: 'VersionTable'.
	 * If not setted it's value is {tableName}_version
	 */
	public $createdByField = "created_by";
	public $createdAtField = "created_time";
	public $versionCommentField = "version_comment";
	public $versionField = "version";
	public $removeVersioningOnDelete = true;
	

	protected $_createdAt = "";
	protected $_createdBy = "";
	protected $_versionComment = "";
	protected $_lastVersion;
	protected $_versionTable;
    /**
     * @var bool Indicates if version specific properties where populated - used internal for lazy loading
     */
    protected $_propertiesPopulated = false;
    /**
     * @var array A list of attributes which should not be versioned
     *           There are 2 kind of special attributes:
     *            * default = if only this attributes changed, no new version will be created
     *            * static = if a static attribute changed, the change will be done on all versions
     */
    protected $_nonVersionedAttributes = array(
                                            'default' => array(),
                                            'static' => array()
                                         );
    /**
     * @var array A buffer for "oldAttributes" in order to check which attributes changed and which not
     */
    protected $_oldattributes = array();

	public function afterSave($event)
	{
        //First we need to determine if we need to save a new version:
        $newVersionRequired = false;

        $newattributes = $this->getOwner()->getAttributes();
        $oldattributes = $this->getOldAttributes();

        foreach ($newattributes as $name => $value) {
            if (in_array($name, $this->_nonVersionedAttributes['default'])) {
                //If it is in the default list, we will not create a new version, even if it was changed
            } else {
                if (!empty($oldattributes)) {
                    $old = $oldattributes[$name];
                } else {
                    $old = null;
                }
                if ($value != $old) {
                    if (in_array($name, $this->_nonVersionedAttributes['static'])) {
                        //If a static member changed, we dont need to create a new version, but we need to change all appearances
                        $this->saveStaticAttribute($name,$value);
                    } else {
                        //We have a real change of a value we want to "version"
                        $newVersionRequired = true;
                    }
                }
            }
        }

        if ($newVersionRequired) {
            Yii::app()->db->createCommand()->insert($this->versionTable, $this->versionedAttributes);
            $version = Yii::app()->db->getLastInsertID();
            Yii::app()->db->createCommand()->update($this->getOwner()->tableName(), array(
                $this->versionField => $version,
                ), 'id=:id', array(':id' => $this->getOwner()->id)
            );
            $this->getOwner()->{$this->versionField} = $version;
            $this->_lastVersion = $version;
        } else {
            //If not we need to update at least the current version with the changes
            $updateFields = array();
            foreach ($this->_nonVersionedAttributes['default'] as $fieldName) {
                $updateFields[$fieldName] = $newattributes[$fieldName];
            }
            Yii::app()->db->createCommand()->update($this->versionTable, $updateFields
                , 'id=:id AND '.$this->versionField.'=:version', array(':id' => $this->getOwner()->id, ':version' => $this->getVersion())
            );
        }

        //Saved values are the new old values
        $this->setOldAttributes($this->getOwner()->getAttributes());
	}

	public function afterDelete($event)
	{
		if($this->removeVersioningOnDelete) {
			$this->deleteVersioning(false);
		}
	}

    public function afterFind($event)
    {
        // Save old values
        $this->setOldAttributes($this->getOwner()->getAttributes());
    }

    public function getOldAttributes()
    {
        return $this->_oldattributes;
    }

    public function setOldAttributes($value)
    {
        $this->_oldattributes=$value;
    }

    public function getPropertiesPopulated()
    {
        return $this->_propertiesPopulated;
    }

    public function setPropertiesPopulated($value)
    {
        $this->_propertiesPopulated=(bool)$this->setAttribute($value);
    }

	/**
	 * Return the name of the version table for the model
	 * Default to {tableName}_version
	 * @return String return the name of the version table for the model
	 */
	public function getVersionTable()
	{
		if($this->_versionTable !== null) {
			return $this->_versionTable;
		} else {
			return $this->getOwner()->tableName() . "_version";
		}
	}

	public function setVersionTable($table)
	{
		$this->_versionTable = $this->setAttribute($table);
	}

    public function getNonVersionedAttributes()
    {
        return $this->_nonVersionedAttributes;
    }

    public function setNonVersionedAttributes($attributes)
    {
        if (is_array($attributes)) {
            $nonVersionedAttributes = array(
                'default' => array(),
                'static' => array()
            );
            if (isset($attributes['default']) && is_array($attributes['default'])) {
                foreach ($attributes['default'] as $item) {
                    if (is_string($item)) {
                        $nonVersionedAttributes['default'][] = $item;
                    }
                }
            }
            if (isset($attributes['static']) && is_array($attributes['static'])) {
                foreach ($attributes['static'] as $item) {
                    if (is_string($item)) {
                        $nonVersionedAttributes['static'][] = $item;
                    }
                }
            }
            $this->_nonVersionedAttributes = $nonVersionedAttributes;
        }
        return $this->_nonVersionedAttributes;
    }


    public function setVersionCreatedBy($createdBy)
    {
        if (!$this->propertiesPopulated) {
            $this->loadVersionProperties();
        }
        $this->_createdBy = $this->setAttribute($createdBy);
    }

    public function getVersionCreatedBy()
	{
        if (!$this->propertiesPopulated) {
            $this->loadVersionProperties();
        }
        return $this->_createdBy;
	}

	public function setVersionComment($versionComment)
	{
        if (!$this->propertiesPopulated) {
            $this->loadVersionProperties();
        }
        $this->_versionComment = $this->setAttribute($versionComment);
	}

	public function getVersionComment()
	{
        if (!$this->propertiesPopulated) {
            $this->loadVersionProperties();
        }
		return $this->_versionComment;
	}

	public function getVersionCreatedAt()
	{
        if (!$this->propertiesPopulated) {
            $this->loadVersionProperties();
        }
		if($this->_createdAt !== null) {
			return $this->_createdAt;
		} else {
			return time();
		}
	}

	public function setVersionCreatedAt($versionCreatedAt)
	{
        if (!$this->propertiesPopulated) {
            $this->loadVersionProperties();
        }
		$this->_createdAt = $versionCreatedAt;
	}

	/**
	* @return Return if the model is at its last version
	*/
	public function isLastVersion() 
	{
		return $this->getOwner()->{$this->versionField} === $this->getLastVersionNumber();
	}
	
	/**
	* @return int Return the version number of the model
	*/
	public function getVersion() 
	{
		if ($this->getOwner()->{$this->versionField} == null) {
			return 0;
		} else {
			return $this->getOwner()->{$this->versionField};
		}
	}
	
	/**
	* @return int Return the last version number of the model
	*/
	public function getLastVersionNumber()
	{
		if($this->_lastVersion !== null) {
			return $this->_lastVersion;
		} else {
			$lastVersion = Yii::app()->db->createCommand()
			    ->select("MAX($this->versionField) as version_number")
			    ->from($this->versionTable)
			    ->where('id=:id', array(':id'=>$this->getOwner()->primaryKey))
			    ->queryRow();
			$this->_lastVersion = $lastVersion['version_number'];
			return $this->_lastVersion;
		}
			
	}

	/**
	 * Remove all the versioned data from the version table
	 * @param  boolean $updateVersion Wheither or not the original model version must be resetted
	 */
	public function deleteVersioning($updateVersion = true)
	{
		Yii::app()->db->createCommand()->delete($this->versionTable, 'id=:id', array(
			':id'=>$this->getOwner()->primaryKey
			)
		);
		if($updateVersion) {
			Yii::app()->db->createCommand()->update($this->getOwner()->tableName(), array(
				$this->versionField => 0,
				), 'id=:id', array(':id' => $this->getOwner()->id)
			);
            $this->setVersionComment("");
            $this->setVersionCreatedBy("");
            $this->setVersionCreatedAt(0);
            $this->getOwner()->{$this->versionField} = null;
		}
	}

	/**
	 * Return all the versions of the Active Record Object
	 * @return array List of the different versions of the object
	 */
	public function getAllVersions()
	{
		$allVersionsArray = Yii::app()->db->createCommand()
			    ->select('*')
			    ->from($this->versionTable)
			    ->where('id=:id', array(':id'=>$this->getOwner()->primaryKey))
			    ->order($this->versionField.' ASC')
			    ->queryAll();
	    if(!empty($allVersionsArray)) {
	    	return $this->populateActiveRecords($allVersionsArray);	
	    } else {
	    	return array();
	    }
	}
	
	/**
	* Return the n last versions of the model.
	* @param int $number Number of the last versions to return. Default to 1.
	* @return array return an array containing the last versions or 
	* an empty array if no versions are available
	*/
	public function getLastVersions($number = 1)
	{
		$lastVersionsArray = Yii::app()->db->createCommand()
			    ->select('*')
			    ->from($this->versionTable)
			    ->where('id=:id', array(':id'=>$this->getOwner()->primaryKey))
                ->order($this->versionField.' DESC')
			    ->limit($number)
                ->queryAll();
	    if(!empty($lastVersionsArray)) {
	    	return $this->populateActiveRecords($lastVersionsArray);
	    } else {
	    	return array();
	    }
	}
	
	/**
	* Return a version of the model or false if the version number doesn't exist
	* @param int $versionNumber Number of the version to return
	* @return mixed return an active record corresponding to the version number
	* or false if it doesn't exist in the db
	*/
	public function getOneVersion($versionNumber) 
	{
		$versionArray = Yii::app()->db->createCommand()
			    ->select('*')
			    ->from($this->versionTable)
			    ->where(
			    	"id=:id AND $this->versionField=:version",
			    	array(
			    		':id'=>$this->getOwner()->primaryKey, 
			    		':version'=>$versionNumber,
			    		)
		    	)
			    ->queryRow();
	    if($versionArray) {
	    	return $this->populateNewRecord($versionArray, get_class($this->getOwner()));
	    } else {
	    	return false;
	    }
	    
	}
	
	/**
	* Convert the model to the given version
    * Attention: This action may destroy any dataintegrity of the model as the attributes will be changed to the state of the old version.
	* @param int $versionNumber The version to convert to
	* @return bool true if everything went fine, false otherwise
	*/
	public function toVersion($versionNumber)
	{
		$versionArray = Yii::app()->db->createCommand()
			    ->select('*')
			    ->from($this->versionTable)
			    ->where(
			    	"id=:id AND $this->versionField=:version",
			    	array(
			    		':id'=>$this->getOwner()->primaryKey, 
			    		':version'=>$versionNumber,
			    		)
		    	)
			    ->queryRow();
	    if($versionArray) {
            /**
             * We need to save the version conversion directly into the database,
             * if not it is nothing else then getOneVersion.
             * If we require the user to save the model via $model->save() it will just create a new
             * version on top. (therefore destroying any sense into a "toVersion" method.
             */
            $dbArray = $this->unsetVersionedAttributes($versionArray);
            unset($dbArray['id']);
            $dbArray[$this->versionField] = $versionNumber;
            Yii::app()->db->createCommand()->update($this->getOwner()->tableName(),
                $dbArray, 'id=:id', array(':id' => $this->getOwner()->id)
            );
	    	$this->populateActiveRecord($versionArray, $this->getOwner());
	    	return true;
	    } else {
	    	return false;
	    }
	}
	
	/**
	* Compare 2 versions of the model
	* the number of the 2 version
	* @return mixed return an array containing the differences or false
	* if a version hasn't been found in the db
	*/
	public function compareVersions($version1, $version2)
	{
		$versionsArray = Yii::app()->db->createCommand()
			    ->select('*')
			    ->from($this->versionTable)
			    ->where(
					array('and', 'id=:id', array('or', $this->versionField.' =:version1',$this->versionField.' =:version2')),
					array(
						':id'=>$this->getOwner()->primaryKey,
						':version1' => $version1,
						':version2' => $version2,
					)
				)
			    ->order($this->versionField." ASC")
			    ->queryAll();
	    if(!empty($versionsArray)&& count($versionsArray) == 2) {
			//Watch attributes changing from one version to the other and put them in the array
			//penser � unset les attributs de version (version, comment, created by, created at)
			$differences = array();
			foreach($versionsArray[0] as $index => $value) {
				if(isset($versionsArray[1][$index]) && $value !== $versionsArray[1][$index]) {
					$differences[$index] = array($versionsArray[0][$this->versionField] => $value, $versionsArray[1][$this->versionField] => $versionsArray[1][$index]);
				}
			}
			$differences = $this->unsetVersionedAttributes($differences);
	    	return $differences;
	    } else {
	    	return false;
	    }
	}
	
	/**
	* Compare the actual model to the given version
	* @param int $versionNumber Version number to compare to
	* @return mixed An array containing the differences or false if the version number
	* doesn't exist in the db
	*/
	public function compareTo($versionNumber)
	{
		$thisVersion = $this->getOwner()->getAttributes(false);
		$versionArray = Yii::app()->db->createCommand()
			    ->select('*')
			    ->from($this->versionTable)
			    ->where(
			    	"id=:id AND $this->versionField=:version",
			    	array(
			    		':id'=>$this->getOwner()->primaryKey, 
			    		':version'=>$versionNumber,
			    		)
		    	)
			    ->queryRow();
		if($versionArray) {
			$differences = array();
			$thisVersion = $this->unsetVersionedAttributes($this->getOwner()->getAttributes(false));
			foreach($thisVersion as $index => $value) {
				if(isset($versionArray[$index]) && $value !== $versionArray[$index]) {
					$differences[$index] = array('actual' => $value, $versionArray[$this->versionField] => $versionArray[$index]);
				}
			}
			return $differences;
		} else {
			return false;
		}
	}
	
	/**
	* Get the attributes to add to the version table:
	* - the attributes from the model (also the not safe ones)
	* - The version attributes (createdBy, ...)
	* @return array an array containing the attributes
	*/
	protected function getVersionedAttributes()
	{
		$this->VersionCreatedAt = date('Y-m-d H:i:s', time());

		$versionedAttributes = $this->getOwner()->getAttributes(false);
		$versionedAttributes[$this->createdByField] = $this->versionCreatedBy;
		$versionedAttributes[$this->createdAtField] = $this->VersionCreatedAt;
		$versionedAttributes[$this->versionCommentField] = $this->versionComment;

		//we don't save the actual version number in the version table since it'll be automatically incremented
		unset($versionedAttributes[$this->versionField]);
		return $versionedAttributes;
	}
	
	/**
	* Unset the versionned attributes that could be returned from sql requests
	* @param array $array the array to unset
	*/
	protected function unsetVersionedAttributes($array)
	{
		unset($array[$this->versionField]);
		unset($array[$this->createdAtField]);
		unset($array[$this->createdByField]);
		unset($array[$this->versionCommentField]);
		return $array;
	}

	protected function setAttribute($value)
	{
		if($value == null) {
			return "";
		} else {
			return $value;
		}
	}
	
	/**
	* Create some Active Records from an array of their values
	* @param array $values Array of the values returned from the db
	* @return array return an array containing the CactiveRecords models
	*/
	protected function populateActiveRecords($values) 
	{
		$className = get_class($this->getOwner());
		$activeRecords = array();
		foreach($values as $version) {	
			$activeRecords[] = $this->populateNewRecord($version, $className);
		}
		return $activeRecords;
	}
	
	/**
	* Create a new active record object and fill it with the given value
	* @param array $values the values that the active record object need to be filled with
	* @param string $className Name of the class object to create
	* @return CActiveRecord Return the newly created object populated
	*/
	protected function populateNewRecord($values, $className)
	{
        $model = $this->populateActiveRecord($values, new $className());
        if ($model->getPrimaryKey()) {
            $model->setOldAttributes($model->getAttributes());
            $model->setIsNewRecord(false);
        }
		return $model;

	}
	
	/**
	* Populate the given Active Record with the given values.
	* @param array $values The values to put in the Active record
	* @param CActiveRecord $model the object to populate
	* @return CActiveRecord return the populate active record
	*/
	protected function populateActiveRecord($values, $model)
	{
		$model->versionComment = $values[$this->versionCommentField];
		$model->versionCreatedBy = $values[$this->createdByField];
		$model->versionCreatedAt = $values[$this->createdAtField];
		$model->setAttributes($values, false);
        $model->setPropertiesPopulated(true);
		return $model;
	}

    /**
     * Load version related propertie and populate them.
     */
    protected function loadVersionProperties() {
        if (!$this->propertiesPopulated) {
            $this->propertiesPopulated = true;
            if ($this->getOwner()->isNewRecord) {
                return true;
            }
            $versionArray = Yii::app()->db->createCommand()
                ->select('*')
                ->from($this->versionTable)
                ->where(
                    "id=:id AND $this->versionField=:version",
                    array(
                        ':id'=>$this->getOwner()->primaryKey,
                        ':version'=>$this->getOwner()->getVersion(),
                    )
                )
                ->queryRow();
            if($versionArray) {
                $model = $this->getOwner();
                $model->versionComment = $versionArray[$this->versionCommentField];
                $model->versionCreatedBy = $versionArray[$this->createdByField];
                $model->versionCreatedAt = $versionArray[$this->createdAtField];
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Saving a static attribute - which means the attribute will be changed in all versions.
     * @param $name - name of the attribute
     * @param $value - new value
     */
    protected function saveStaticAttribute($name,$value) {
        Yii::app()->db->createCommand()->update($this->versionTable, array(
                $name => $value,
            ), 'id=:id', array(':id' => $this->getOwner()->id)
        );
    }
	
}
