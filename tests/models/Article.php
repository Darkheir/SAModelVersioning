<?php

/**
 * This is the model class for table "SAMVTest_article".
 *
 * The followings are the available columns in table 'SAMVTest_article':
 * @property integer $id
 * @property integer $version
 * @property string $title
 * @property string $content
 * @property integer $approved
 * @property integer $visible
 * @property integer $deleted
 */
class Article extends CActiveRecord
{
    public $title = "";
    public $content = "";
    public $approved = false;
    public $visible = false;
    public $deleted = false;

	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Article the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'SAMVTest_article';
	}

    public function behaviors(){
        return array(
            'modelVersioning' => array(
                'class' => 'ext.SAModelVersioning',
                /* Not yet implemented, therefore crashing test suite
                'nonVersionedAttributes' => array(
                        'approved',
                        'visible',
                    ),
                    'static' => array(
                        'deleted'
                    ),
                ),
                */
            ),
        );
    }

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('content', 'required'),
			array('title', 'length', 'max'=>45),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, version, title, content, approved, visible, deleted', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'version' => 'Version',
			'title' => 'Title',
			'content' => 'Content',
			'approved' => 'Approved',
			'visible' => 'Visible',
			'deleted' => 'Deleted',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('id',$this->id);
		$criteria->compare('version',$this->version);
		$criteria->compare('title',$this->title,true);
		$criteria->compare('content',$this->content,true);
		$criteria->compare('approved',$this->approved);
		$criteria->compare('visible',$this->visible);
		$criteria->compare('deleted',$this->deleted);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
}