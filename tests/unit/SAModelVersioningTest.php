<?php
class SAModelVersioningTest extends CDbTestCase
{
	public $fixtures=array(
		'articles' => 'Article',
        'comments' => 'Comment',
        'articleVersions' => ':SAMVTest_article_version',
        'commentVersions' => ':SAMVTest_comment_history',
	);

    public static function setUpBeforeClass()
    {
        $path = Yii::app()->getBasePath();
        $path.= "/schema/SAMVUp.sql";
        $content = file_get_contents($path);
        $command = Yii::app()->db->createCommand($content);
        $command->execute();
        $command->getPdoStatement()->closeCursor();
    }

    protected function setUp()
    {
        parent::setUp();
    }

    public static function tearDownAfterClass()
    {
        $path = Yii::app()->getBasePath();
        $path.= "/schema/SAMVDown.sql";
        $content = file_get_contents($path);
        $command = Yii::app()->db->createCommand($content);
        $command->execute();
        $command->getPdoStatement()->closeCursor();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    public function testAfterSave() {
        //Creation of model: A new version should have been generated
        $article = new Article();
        $article->content = "My little Article";
        $article->approved = false;
        $article->visible = false;

        $article->versionCreatedBy = "User";
        $article->versionComment = "Creating Article";
        $expectedTime = date('Y-m-d H:i:s', time());
        $article->save();
        $article_id = $article->getPrimaryKey();
        $expectedVersion = $article->version;

        $article = Article::model()->findByPk($article_id);
        $this->assertEquals($expectedVersion,$article->getVersion());
        $versionEntry = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where('id=:id AND version=:version', array(':id' => $article_id, ':version' => $expectedVersion))
            ->queryRow();
        $this->assertNotEmpty($versionEntry);

        $expected =  array(
            'id' => $article_id,
            'version' => $expectedVersion,
            'title' => "",
            'content' => "My little Article",
            'approved' => 0,
            'visible' => 0,
            'deleted' => 0,
            'version_comment' => "Creating Article",
            'created_by' => "User",
            'created_time' => $expectedTime,
        );
        $this->assertEquals($expected,$versionEntry);

        //Admin is now entering corrections and approving the content - a new version should be created because of changing the content
        $expectedVersion++;

        $article->content = "Our (*edited by Admin) little Article";
        $article->approved = true;
        $article->versionCreatedBy = "Admin";
        $article->versionComment = "Review and approval";
        $expectedTime = date('Y-m-d H:i:s', time());
        $article->save();

        $article = Article::model()->findByPk($article_id);
        $this->assertEquals($expectedVersion,$article->getVersion());
        $versionEntry = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where('id=:id AND version=:version', array(':id' => $article_id, ':version' => $expectedVersion))
            ->queryRow();
        $this->assertNotEmpty($versionEntry);

        $expected =  array(
            'id' => $article_id,
            'version' => $expectedVersion,
            'title' => "",
            'content' => "Our (*edited by Admin) little Article",
            'approved' => 1,
            'visible' => 0,
            'deleted' => 0,
            'version_comment' => "Review and approval",
            'created_by' => "Admin",
            'created_time' => $expectedTime,
        );
        $this->assertEquals($expected,$versionEntry);


        //Now making the article visible. It should not generate a new version
        $article->visible = true;
        $article->save();

        $article = Article::model()->findByPk($article_id);
        $this->assertEquals($expectedVersion,$article->getVersion());
        $versionEntry = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where('id=:id AND version=:version', array(':id' => $article_id, ':version' => $expectedVersion))
            ->queryRow();
        $this->assertNotEmpty($versionEntry);

        $expected =  array(
            'id' => $article_id,
            'version' => $expectedVersion,
            'title' => "",
            'content' => "Our (*edited by Admin) little Article",
            'approved' => 1,
            'visible' => 1,
            'deleted' => 0,
            'version_comment' => "Review and approval",
            'created_by' => "Admin",
            'created_time' => $expectedTime,
        );
        $this->assertEquals($expected,$versionEntry);


        //User edits the article but actually is doing nothing -> no new version will be generated
        $article->versionCreatedBy = "User";
        $article->versionComment = "Doing nothing";
        $article->save();

        $article = Article::model()->findByPk($article_id);
        $this->assertEquals($expectedVersion,$article->getVersion());
        $versionEntry = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where('id=:id AND version=:version', array(':id' => $article_id, ':version' => $expectedVersion))
            ->queryRow();
        $this->assertNotEmpty($versionEntry);

        $expected =  array(
            'id' => $article_id,
            'version' => $expectedVersion,
            'title' => "",
            'content' => "Our (*edited by Admin) little Article",
            'approved' => 1,
            'visible' => 1,
            'deleted' => 0,
            'version_comment' => "Review and approval",
            'created_by' => "Admin",
            'created_time' => $expectedTime,
        );
        $this->assertEquals($expected,$versionEntry);

        //Now the user edits the content, a new version should be created
        $expectedVersion++;

        $article->content = "A better version of our article.";
        $article->approved = false;
        $article->versionCreatedBy = "User";
        $article->versionComment = "Editing content";
        $expectedTime = date('Y-m-d H:i:s', time());
        $article->save();

        $article = Article::model()->findByPk($article_id);
        $this->assertEquals($expectedVersion,$article->getVersion());
        $versionEntry = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where('id=:id AND version=:version', array(':id' => $article_id, ':version' => $expectedVersion))
            ->queryRow();
        $this->assertNotEmpty($versionEntry);

        $expected =  array(
            'id' => $article_id,
            'version' => $expectedVersion,
            'title' => "",
            'content' => "A better version of our article.",
            'approved' => 0,
            'visible' => 1,
            'deleted' => 0,
            'version_comment' => "Editing content",
            'created_by' => "User",
            'created_time' => $expectedTime,
        );
        $this->assertEquals($expected,$versionEntry);

        //The admin doesnt make any corrections and just approves it -> no new version
        $article->approved = true;
        $article->save();

        $article = Article::model()->findByPk($article_id);
        $this->assertEquals($expectedVersion,$article->getVersion());
        $versionEntry = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where('id=:id AND version=:version', array(':id' => $article_id, ':version' => $expectedVersion))
            ->queryRow();
        $this->assertNotEmpty($versionEntry);

        $expected =  array(
            'id' => $article_id,
            'version' => $expectedVersion,
            'title' => "",
            'content' => "A better version of our article.",
            'approved' => 1,
            'visible' => 1,
            'deleted' => 0,
            'version_comment' => "Editing content",
            'created_by' => "User",
            'created_time' => $expectedTime,
        );
        $this->assertEquals($expected,$versionEntry);

        /**
         * The user decides to delete the item (but it will be kept in the system flagged as deleted)
         * - the static flag deleted needs to be set to true for all the versions
         */
        $article->deleted = true;
        $article->save();

        $article = Article::model()->findByPk($article_id);
        $this->assertEquals($expectedVersion,$article->getVersion());
        $versionEntry = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where('id=:id AND version=:version', array(':id' => $article_id, ':version' => $expectedVersion))
            ->queryRow();
        $this->assertNotEmpty($versionEntry);

        $expected =  array(
            'id' => $article_id,
            'version' => $expectedVersion,
            'title' => "",
            'content' => "A better version of our article.",
            'approved' => 1,
            'visible' => 1,
            'deleted' => 1,
            'version_comment' => "Editing content",
            'created_by' => "User",
            'created_time' => $expectedTime,
        );
        $this->assertEquals($expected,$versionEntry);

        $versions = $article->getAllVersions();
        $this->assertTrue(is_array($versions) && count($versions) == 3);
        foreach ($versions as $version) {
            $this->assertTrue($version->deleted == true);
        }
    }

    public function testAfterDelete() {
        //Most of it is tested in delete versioning, we only have to test if it was called basically
        $article = Article::model()->findByPk(1);
        $comment = Comment::model()->findByPk(1);

        $article->delete();
        $comment->delete();

        $versionEntries = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where(array('in', 'id', array(1)))
            ->queryAll();
        $this->assertEmpty($versionEntries,"All article versions deleted");
        $versionEntries = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Comment::model()->getVersionTable())
            ->where(array('in', 'id', array(1)))
            ->queryAll();
        $this->assertNotEmpty($versionEntries,"Comment versions not deleted");
    }

    public function testAfterFind()
    {
        $article = Article::model()->findByPk(1);
        $this->assertEquals($article->getAttributes(),$article->getOldAttributes());
    }

    public function testGetOldAttributes()
    {
        //Not much to test for a simple getter method, so we will test if under all circumstances the oldAttributes are updated.
        $article = Article::model()->findByPk(1);
        $this->assertEquals($article->getAttributes(),$article->getOldAttributes());

        $article2 = $article->getOneVersion(2);
        $this->assertEquals($article2->getAttributes(),$article2->getOldAttributes());

        $article2->content = "Test1234";
        $this->assertNotEquals($article2->getAttributes(),$article2->getOldAttributes());

        $article2->save();
        $this->assertEquals($article2->getAttributes(),$article2->getOldAttributes());
    }

    public function testSetOldAttributes()
    {
        $article = new Article();
        $expectation = array(
          'content' => "HelloSetOldAttributes",
        );
        $article->setOldAttributes($expectation);
        $this->assertEquals($expectation,$article->getOldAttributes());
    }

    public function testGetNonVersionedAttributes()
    {
        $expectation = array(
            'default' => array(
                'approved',
                'visible',
            ),
            'static' => array(
                'deleted'
            ),
        );

        $this->assertEquals($expectation,Article::model()->getNonVersionedAttributes());

        $expectation = array(
            'default' => array(
            ),
            'static' => array(
            ),
        );

        $this->assertEquals($expectation,Comment::model()->getNonVersionedAttributes());
    }

    public function testSetNonVersionedAttributes()
    {
        $expectation = array(
            'default' => array(
                'approved',
                'visible',
            ),
            'static' => array(
                'deleted'
            ),
        );

        Comment::model()->setNonVersionedAttributes($expectation);

        $this->assertEquals($expectation,Comment::model()->getNonVersionedAttributes());
    }

    public function testGetVersionTable() {
        $articleVersionTable = Article::model()->getVersionTable();
        $this->assertTrue($articleVersionTable === "SAMVTest_article_version","Returning default version table.");

        $commentVersionTable = Comment::model()->getVersionTable();
        $this->assertTrue($commentVersionTable === "SAMVTest_comment_history","Returning custom version table.");
    }

    public function testSetVersionTable() {
        Comment::model()->setVersionTable("SomethingSomething");
        $commentVersionTable = Comment::model()->getVersionTable();
        $this->assertTrue($commentVersionTable === "SomethingSomething","Setting custom version table");
        Comment::model()->setVersionTable("SAMVTest_comment_history");
    }

    public function testSetVersionCreatedBy() {
        $article = new Article();
        $article->content = "Test";
        $article->setVersionCreatedBy("TestUser");

        $this->assertTrue($article->getVersionCreatedBy() === "TestUser","Setting VersionCreatedBy Attribute");

        $comment = new Comment();
        $comment->comment = "Test";
        $comment->setVersionCreatedBy("TestUser");

        $this->assertTrue($comment->getVersionCreatedBy() === "TestUser","Setting VersionCreatedBy Attribute on custom table");
    }

    public function testGetVersionCreatedBy() {
        $article = new Article();
        $article->content = "Test";
        $article->setVersionCreatedBy("TestUser");
        $this->assertTrue($article->save(),"Saving article");

        $comment = new Comment();
        $comment->comment = "Test";
        $comment->setVersionCreatedBy("TestUser");
        $this->assertTrue($comment->save(),"Saving comment");

        $article_id = $article->getPrimaryKey();
        $comment_id = $comment->getPrimaryKey();

        //Trying to get the versionCreatedBy via SAModelVersioining functionality
        $article = $article->getLastVersions();
        $article = end($article);
        $comment = $comment->getLastVersions();
        $comment = end($comment);
        $this->assertEquals("TestUser",$article->getVersionCreatedBy(),"Getting VersionCreatedBy Attribute");
        $this->assertEquals("TestUser",$comment->getVersionCreatedBy(),"Getting VersionCreatedBy Attribute on custom table");

        //Trying to get the versionCreatedBy via normal active record operations
        $article = Article::model()->findByPk($article_id);
        $comment = Comment::model()->findByPk($comment_id);
        $this->assertEquals("TestUser",$article->getVersionCreatedBy(),"Getting VersionCreatedBy Attribute");
        $this->assertEquals("TestUser",$comment->getVersionCreatedBy(),"Getting VersionCreatedBy Attribute on custom table");

        //Extra testing: Trying to change and save it again!
        $comment->comment ="Test2";
        $comment->setVersionCreatedBy("TestUser2");
        $this->assertTrue($comment->save(),"Saving comment");

        $comment = Comment::model()->findByPk($comment_id);
        $this->assertEquals("Test2",$comment->comment);
        $this->assertEquals("TestUser2",$comment->getVersionCreatedBy(),"Getting VersionCreatedBy Attribute on custom table");
    }

    public function testSetVersionComment() {
        $article = new Article();
        $article->content = "Test";
        $article->setVersionComment("TestComment");

        $this->assertTrue($article->getVersionComment() === "TestComment","Setting getVersionComment Attribute");

        $comment = new Comment();
        $comment->comment = "Test";
        $comment->setVersionComment("TestComment");

        $this->assertTrue($comment->getVersionComment() === "TestComment","Setting getVersionComment Attribute on custom table");
    }

    public function testGetVersionComment() {
        $article = new Article();
        $article->content = "Test";
        $article->setVersionComment("TestComment");
        $this->assertTrue($article->save(),"Saving article");

        $comment = new Comment();
        $comment->comment = "Test";
        $comment->setVersionComment("TestComment");
        $this->assertTrue($comment->save(),"Saving comment");

        $article_id = $article->getPrimaryKey();
        $comment_id = $comment->getPrimaryKey();

        //Trying to get the getVersionComment via SAModelVersioning functionality
        $article = $article->getLastVersions();
        $article = end($article);
        $comment = $comment->getLastVersions();
        $comment = end($comment);
        $this->assertEquals("TestComment",$article->getVersionComment(),"Getting getVersionComment Attribute");
        $this->assertEquals("TestComment",$comment->getVersionComment(),"Getting getVersionComment Attribute on custom table");

        //Trying to get the getVersionComment via normal active record operations
        $article = Article::model()->findByPk($article_id);
        $comment = Comment::model()->findByPk($comment_id);
        $this->assertEquals("TestComment",$article->getVersionComment(),"Getting getVersionComment Attribute");
        $this->assertEquals("TestComment",$comment->getVersionComment(),"Getting getVersionComment Attribute on custom table");
    }

    public function testGetVersionCreatedAt() {
        $article = Article::model()->findByPk(1);
        $comment = Comment::model()->findByPk(1);

        //Trying to get the getVersionCreatedAt via normal active record operations
        $this->assertEquals("2013-05-28 12:55:24",$article->getVersionCreatedAt(),"Getting getVersionCreatedAt Attribute");
        $this->assertEquals("2013-05-28 12:55:24",$comment->getVersionCreatedAt(),"Getting getVersionCreatedAt Attribute on custom table");

        //Trying to get the getVersionCreatedAt via SAModelVersioning functionality
        $article = $article->getLastVersions();
        $article = end($article);
        $comment = $comment->getLastVersions();
        $comment = end($comment);
        $this->assertEquals("2013-05-28 12:55:24",$article->getVersionCreatedAt(),"Getting getVersionCreatedAt Attribute");
        $this->assertEquals("2013-05-28 12:55:24",$comment->getVersionCreatedAt(),"Getting getVersionCreatedAt Attribute on custom table");
    }

    public function testSetVersionCreatedAt() {
        $article = new Article();
        $article->content = "Test";
        $article->setVersionCreatedAt("2013-05-28 12:55:22");

        $this->assertTrue($article->getVersionCreatedAt() === "2013-05-28 12:55:22","Setting getVersionComment Attribute");

        $comment = new Comment();
        $comment->comment = "Test";
        $comment->setVersionCreatedAt("2013-05-28 12:55:22");

        $this->assertTrue($comment->getVersionCreatedAt() === "2013-05-28 12:55:22","Setting getVersionComment Attribute on custom table");
    }

    public function testIsLastVersion() {
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $articlePreviousVersion = Article::model()->findByPk(2);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);
        $commentPreviousVersion = Comment::model()->findByPk(2);

        $this->assertTrue($articleNewVersion->isLastVersion(),"New Article is last version");
        $this->assertTrue($articleLastVersion->isLastVersion(),"Article 1 is last version");
        $this->assertFalse($articlePreviousVersion->isLastVersion(),"Article 2 is not last version");

        $this->assertTrue($commentNewVersion->isLastVersion(),"New Comment is last version");
        $this->assertTrue($commentLastVersion->isLastVersion(),"Comment 1 is last version");
        $this->assertFalse($commentPreviousVersion->isLastVersion(),"Comment 2 is not last version");
    }

    public function testGetVersion() {
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $articlePreviousVersion = Article::model()->findByPk(2);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);
        $commentPreviousVersion = Comment::model()->findByPk(2);

        $this->assertEquals(0,$articleNewVersion->getVersion() ,"New Article is version 0");
        $this->assertEquals(3,$articleLastVersion->getVersion() ,"Article 1 is version 3");
        $this->assertEquals(1,$articlePreviousVersion->getVersion() ,"Article 2 is version 1");

        $this->assertEquals(0,$commentNewVersion->getVersion() ,"New Comment is version 0");
        $this->assertEquals(3,$commentLastVersion->getVersion() ,"Comment 1 is version 3");
        $this->assertEquals(1,$commentPreviousVersion->getVersion() ,"Comment 2 is version 1");
    }

    public function testGetLastVersionNumber() {
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $articlePreviousVersion = Article::model()->findByPk(2);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);
        $commentPreviousVersion = Comment::model()->findByPk(2);

        $this->assertEquals(0, $articleNewVersion->getLastVersionNumber(),"New Article has last version 0");
        $this->assertEquals(3, $articleLastVersion->getLastVersionNumber(),"Article 1 has last version 3");
        $this->assertEquals(2, $articlePreviousVersion->getLastVersionNumber(),"Article 2 has last version 1");

        $this->assertEquals(0, $commentNewVersion->getLastVersionNumber(),"New Comment has last version 0");
        $this->assertEquals(3, $commentLastVersion->getLastVersionNumber(),"Comment 1 has last version 3");
        $this->assertEquals(2, $commentPreviousVersion->getLastVersionNumber(),"Comment 2 has last version 1");
    }

    public function testDeleteVersioning() {
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $articlePreviousVersion = Article::model()->findByPk(2);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);
        $commentPreviousVersion = Comment::model()->findByPk(2);

        $articleNewVersion->deleteVersioning(false);
        $articleLastVersion->deleteVersioning(false);
        $articlePreviousVersion->deleteVersioning(false);
        $commentNewVersion->deleteVersioning(false);
        $commentLastVersion->deleteVersioning(false);
        $commentPreviousVersion->deleteVersioning(false);

        //Check immediate impacts
        $versionEntries = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where(array('in', 'id', array(1, 2)))
            ->queryAll();
        $this->assertEmpty($versionEntries,"All article versions deleted");
        $versionEntries = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Comment::model()->getVersionTable())
            ->where(array('in', 'id', array(1, 2)))
            ->queryAll();
        $this->assertEmpty($versionEntries,"All comment versions deleted");

        $this->assertEquals(0, $articleNewVersion->getVersion() ,"New Article is version 0");
        $this->assertEquals(3, $articleLastVersion->getVersion() ,"Article 1 is version 3");
        $this->assertEquals(1, $articlePreviousVersion->getVersion() ,"Article 2 is version 1");

        $this->assertEquals(0, $commentNewVersion->getVersion() ,"New Comment is version 0");
        $this->assertEquals(3, $commentLastVersion->getVersion() ,"Comment 1 is version 3");
        $this->assertEquals(1, $commentPreviousVersion->getVersion() ,"Comment 2 is version 1");

        //Check impacts after loading
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $articlePreviousVersion = Article::model()->findByPk(2);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);
        $commentPreviousVersion = Comment::model()->findByPk(2);

        $this->assertEquals(0, $articleNewVersion->getVersion() ,"New Article is version 0");
        $this->assertEquals(3, $articleLastVersion->getVersion() ,"Article 1 is version 3");
        $this->assertEquals(1, $articlePreviousVersion->getVersion() ,"Article 2 is version 1");

        $this->assertEquals(0, $commentNewVersion->getVersion() ,"New Comment is version 0");
        $this->assertEquals(3, $commentLastVersion->getVersion() ,"Comment 1 is version 3");
        $this->assertEquals(1, $commentPreviousVersion->getVersion() ,"Comment 2 is version 1");
    }

    public function testDeleteVersioningUpdateVersioning() {
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $articlePreviousVersion = Article::model()->findByPk(2);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);
        $commentPreviousVersion = Comment::model()->findByPk(2);

        $articleNewVersion->deleteVersioning(true);
        $articleLastVersion->deleteVersioning(true);
        $articlePreviousVersion->deleteVersioning(true);
        $commentNewVersion->deleteVersioning(true);
        $commentLastVersion->deleteVersioning(true);
        $commentPreviousVersion->deleteVersioning(true);

        //Check immediate impacts
        $versionEntries = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Article::model()->getVersionTable())
            ->where(array('in', 'id', array(1, 2)))
            ->queryAll();
        $this->assertEmpty($versionEntries,"All article versions deleted");
        $versionEntries = Yii::app()->db->createCommand()
            ->select('*')
            ->from(Comment::model()->getVersionTable())
            ->where(array('in', 'id', array(1, 2)))
            ->queryAll();
        $this->assertEmpty($versionEntries,"All comment versions deleted");

        $this->assertEquals(0, $articleNewVersion->getVersion() ,"New Article is version 0");
        $this->assertEquals(0, $articleLastVersion->getVersion() ,"Article 1 is version 0");
        $this->assertEquals(0, $articlePreviousVersion->getVersion() ,"Article 2 is version 0");

        $this->assertEquals(0, $commentNewVersion->getVersion() ,"New Comment is version 0");
        $this->assertEquals(0, $commentLastVersion->getVersion() ,"Comment 1 is version 0");
        $this->assertEquals(0, $commentPreviousVersion->getVersion() ,"Comment 2 is version 0");

        //Check impacts after loading
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $articlePreviousVersion = Article::model()->findByPk(2);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);
        $commentPreviousVersion = Comment::model()->findByPk(2);

        $this->assertEquals(0, $articleNewVersion->getVersion() ,"New Article is version 0");
        $this->assertEquals(0, $articleLastVersion->getVersion() ,"Article 1 is version 0");
        $this->assertEquals(0, $articlePreviousVersion->getVersion() ,"Article 2 is version 0");

        $this->assertEquals(0, $commentNewVersion->getVersion() ,"New Comment is version 0");
        $this->assertEquals(0, $commentLastVersion->getVersion() ,"Comment 1 is version 0");
        $this->assertEquals(0, $commentPreviousVersion->getVersion() ,"Comment 2 is version 0");
    }

    public function testGetAllVersions() {
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $articlePreviousVersion = Article::model()->findByPk(2);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);
        $commentPreviousVersion = Comment::model()->findByPk(2);

        $articleNewVersions = $articleNewVersion->getAllVersions();
        $articleLastVersions = $articleLastVersion->getAllVersions();
        $articlePreviousVersions = $articlePreviousVersion->getAllVersions();
        $commentNewVersions = $commentNewVersion->getAllVersions();
        $commentLastVersions = $commentLastVersion->getAllVersions();
        $commentPreviousVersions = $commentPreviousVersion->getAllVersions();

        //Testing amount
        $this->assertTrue(is_array($articleNewVersions) && empty($articleNewVersions));
        $this->assertTrue(is_array($articleLastVersions) && count($articleLastVersions) == 3);
        $this->assertTrue(is_array($articlePreviousVersions) && count($articlePreviousVersions) == 2);

        $this->assertTrue(is_array($commentNewVersions) && empty($commentNewVersions));
        $this->assertTrue(is_array($commentLastVersions) && count($commentLastVersions) == 3);
        $this->assertTrue(is_array($commentPreviousVersions) && count($commentPreviousVersions) == 2);

        //Testing specific records and order
        $this->assertEquals($articleLastVersion->content,$articleLastVersions[2]->content);
        $this->assertEquals($articlePreviousVersion->content,$articlePreviousVersions[0]->content);

        $this->assertEquals($commentLastVersion->comment,$commentLastVersions[2]->comment);
        $this->assertEquals($commentPreviousVersion->comment,$commentPreviousVersions[0]->comment);

        //Testing specific versioning attributes
        $this->assertEquals(1,$commentPreviousVersions[0]->getVersion());
        $this->assertEquals(2,$commentPreviousVersions[1]->getVersion());
        $this->assertEquals(1,$articleLastVersions[0]->getVersion());
        $this->assertEquals(2,$articleLastVersions[1]->getVersion());
        $this->assertEquals(3,$articleLastVersions[2]->getVersion());
        $this->assertEquals("User",$articleLastVersions[2]->getVersionCreatedBy());
    }

    public function testGetLastVersions() {
        $articleNewVersion = new Article();
        $articleLastVersion = Article::model()->findByPk(1);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);

        $lastVersions = $articleNewVersion->getLastVersions();
        $this->assertTrue(is_array($lastVersions) && empty($lastVersions));
        $lastVersions = $commentNewVersion->getLastVersions();
        $this->assertTrue(is_array($lastVersions) && empty($lastVersions));

        $lastVersions = $articleNewVersion->getLastVersions(2);
        $this->assertTrue(is_array($lastVersions) && empty($lastVersions));
        $lastVersions = $commentNewVersion->getLastVersions(2);
        $this->assertTrue(is_array($lastVersions) && empty($lastVersions));

        $lastVersions = $articleLastVersion->getLastVersions();
        $this->assertTrue(is_array($lastVersions) && count($lastVersions) == 1);
        $this->assertInstanceOf('Article',$lastVersions[0]);
        $lastVersions = $commentLastVersion->getLastVersions();
        $this->assertTrue(is_array($lastVersions) && count($lastVersions) == 1);
        $this->assertInstanceOf('Comment',$lastVersions[0]);

        $lastVersions = $articleLastVersion->getLastVersions(2);
        $this->assertTrue(is_array($lastVersions) && count($lastVersions) == 2);
        $this->assertInstanceOf('Article',$lastVersions[0]);
        $this->assertInstanceOf('Article',$lastVersions[1]);
        $this->assertEquals(1,$lastVersions[1]->id);
        $this->assertFalse($lastVersions[1]->isNewRecord);
        $lastVersions = $commentLastVersion->getLastVersions(2);
        $this->assertTrue(is_array($lastVersions) && count($lastVersions) == 2);
        $this->assertInstanceOf('Comment',$lastVersions[0]);
        $this->assertInstanceOf('Comment',$lastVersions[1]);
        $this->assertEquals("Review and Approval", $lastVersions[1]->getVersionComment());
        $this->assertEquals("Admin", $lastVersions[1]->getVersionCreatedBy());
        $this->assertEquals("2013-05-28 12:55:23", $lastVersions[1]->getVersionCreatedAt());

        $lastVersions = $articleLastVersion->getLastVersions(10);
        $this->assertTrue(is_array($lastVersions) && count($lastVersions) == 3);
        $this->assertInstanceOf('Article',$lastVersions[2]);
        $lastVersions = $commentLastVersion->getLastVersions(10);
        $this->assertTrue(is_array($lastVersions) && count($lastVersions) == 3);
        $this->assertInstanceOf('Comment',$lastVersions[2]);
    }

    public function testGetOneVersion() {
        $articleLastVersion = Article::model()->findByPk(1);
        $commentNewVersion = new Comment();
        $commentLastVersion = Comment::model()->findByPk(1);

        $this->assertFalse($articleLastVersion->getOneVersion(4));
        $this->assertFalse($commentNewVersion->getOneVersion(1));
        $this->assertFalse($commentLastVersion->getOneVersion(10));

        $article = $articleLastVersion->getOneVersion(3);
        $comment = $commentLastVersion->getOneVersion(2);

        $this->assertInstanceOf('Article',$article);
        $this->assertInstanceOf('Comment',$comment);

        $this->assertEquals("Review and Approval", $comment->getVersionComment());
        $this->assertEquals("Admin", $comment->getVersionCreatedBy());
        $this->assertEquals("2013-05-28 12:55:23", $comment->getVersionCreatedAt());

        $this->assertEquals("Corrected Content", $article->content);
        $this->assertEquals(1, $article->id);
        $this->assertFalse($article->isNewRecord);
    }

    public function testToVersion() {
        $article = Article::model()->findByPk(1);
        $comment = Comment::model()->findByPk(1);

        $this->assertFalse($article->toVersion(10));
        $this->assertFalse($comment->toVersion(10));

        $this->assertTrue($article->toVersion(2));
        $this->assertTrue($comment->toVersion(2));

        //Direct impact of revisioning
        $this->assertEquals("Review and Approval", $comment->getVersionComment());
        $this->assertEquals("Admin", $comment->getVersionCreatedBy());
        $this->assertEquals("2013-05-28 12:55:23", $comment->getVersionCreatedAt());

        $this->assertEquals("Corrected Content for Approval", $article->content);
        $this->assertEquals(1, $article->id);
        $this->assertFalse($article->isNewRecord);

        $article = Article::model()->findByPk(1);
        $comment = Comment::model()->findByPk(1);

        //Impact after loading of database items
        $this->assertEquals("Review and Approval", $comment->getVersionComment());
        $this->assertEquals("Admin", $comment->getVersionCreatedBy());
        $this->assertEquals("2013-05-28 12:55:23", $comment->getVersionCreatedAt());

        $this->assertEquals("Corrected Content for Approval", $article->content);
        $this->assertEquals(1, $article->id);
        $this->assertFalse($article->isNewRecord);
    }

    public function testCompareVersions() {
        $article = Article::model()->findByPk(1);
        $comment = Comment::model()->findByPk(1);

        $this->assertFalse($article->compareVersions(1,10));
        $this->assertFalse($comment->compareVersions(10,1));

        $differences = $article->compareVersions(1,2);
        $expected = array(
            'content' => array(
                1 => "Test Content",
                2 => "Corrected Content for Approval",
            ),
            'approved' => array(
                1 => 0,
                2 => 1,
            ),
        );
        $this->assertTrue(is_array($differences));
        $this->assertEquals($expected,$differences);

        $differences = $comment->compareVersions(2,3);
        $expected = array(
            'comment' => array(
                2 => "Corrected Content for Approval",
                3 => "Corrected Content",
            ),
        );
        $this->assertTrue(is_array($differences));
        $this->assertEquals($expected,$differences);
    }

    public function testCompareTo() {
        $article = Article::model()->findByPk(1);
        $comment = Comment::model()->findByPk(1);

        $this->assertFalse($article->compareTo(10));
        $this->assertFalse($comment->compareTo(10));

        $differences = $article->compareTo(3);
        $expected = array(
        );
        $this->assertTrue(is_array($differences));
        $this->assertEquals($expected,$differences);


        $differences = $comment->compareTo(2);
        $expected = array(
            'comment' => array(
                'actual' => "Corrected Content",
                2 => "Corrected Content for Approval",
            ),
        );
        $this->assertTrue(is_array($differences));
        $this->assertEquals($expected,$differences);
    }
}