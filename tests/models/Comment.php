<?php

/**
 * This is the model class for table "samvtest_comment".
 *
 * The followings are the available columns in table 'samvtest_comment':
 * @property integer $id
 * @property integer $iter
 * @property string $comment
 */
class Comment extends CActiveRecord
{
    public $comment = "";

	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Comment the static model class
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
		return 'samvtest_comment';
	}

    public function behaviors(){
        return array(
            'modelVersioning' => array(
                'class' => 'ext.SAModelVersioning',
                'versionTable' => 'SAMVTest_comment_history',
                'createdByField' => 'user',
                'createdAtField' => 'timestamp',
                'versionCommentField' => 'edit_reason',
                'versionField' => 'iter',
                'removeVersioningOnDelete' => false,
            )
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
			array('comment', 'required'),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, iter, comment', 'safe', 'on'=>'search'),
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
			'iter' => 'Version',
			'comment' => 'Comment',
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
		$criteria->compare('iter',$this->iter);
		$criteria->compare('comment',$this->comment,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
}