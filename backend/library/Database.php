<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace backend\library;

use yii;

use common\library\Basewind;

/**
 * @Id Database.php 2018.10.30 $
 * @author mosir
 */
 
class Database
{
    /**
     * 文件指针
     * @var resource
     */
    private $fp;

    /**
     * 备份文件信息 part - 卷号，name - 文件名
     * @var array
     */
    private $file;

    /**
     * 当前打开文件大小
     * @var integer
     */
    private $size = 0;

    /**
     * 备份配置
     * @var integer
     */
    private $config;

    /**
     * 数据库备份构造方法
     * @param array  $file   备份或还原的文件信息
     * @param array  $config 备份配置信息
     * @param string $type   执行类型，export - 备份数据， import - 还原数据
     */
    public function __construct($file, $config, $type = 'export') {
        $this->file   = $file;
        $this->config = $config;
    }

    /**
     * 打开一个卷，用于写入数据
     * @param  integer $size 写入数据的大小
     */
    private function open($size)
	{
        if($this->fp){
            $this->size += $size;
            if($this->size > $this->config['part']){
                $this->config['compress'] ? @gzclose($this->fp) : @fclose($this->fp);
                $this->fp = null;
                $this->file['part']++;
                Yii::$app->session->set('backup_file', $this->file);
                $this->create();
            }
        } else {
            $backuppath = $this->config['path'];
            $filename   = "{$backuppath}{$this->file['name']}-{$this->file['part']}.sql";
            if($this->config['compress']){
                $filename = "{$filename}.gz";
                $this->fp = @gzopen($filename, "a{$this->config['level']}");
            } else {
                $this->fp = @fopen($filename, 'a');
            }
            $this->size = filesize($filename) + $size;
        }
    }

    /**
     * 写入初始数据
     * @return boolean true - 写入成功，false - 写入失败
     */
    public function create()
	{
        $sql  = "-- -----------------------------\n";
        $sql .= "-- ShopWind MySQL Data Transfer \n";
        $sql .= "-- \n";
		$sql .= "-- ShopWind : " . Basewind::getVersion() . "\n";
        $sql .= "-- Host     : " . Yii::$app->db->dsn . "\n";
        $sql .= "-- Port     : " . Yii::$app->db->dsn . "\n";
        $sql .= "-- Database : " . Yii::$app->db->dsn . "\n";
        $sql .= "-- \n";
		$sql .= "-- OS : " . PHP_OS . "\n";
		$sql .= "-- PHP VERSION : " . PHP_VERSION . "\n";
		$sql .= "-- MYSQL VERSION : " . Yii::$app->db->getServerVersion() . "\n";
        $sql .= "-- \n";
        $sql .= "-- Part : #{$this->file['part']}\n";
		$sql .= "-- Compress : {$this->config['compress']}\n";
        $sql .= "-- Date : " . date("Y-m-d H:i:s") . "\n";
        $sql .= "-- -----------------------------\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        return $this->write($sql);
    }

    /**
     * 写入SQL语句
     * @param  string $sql 要写入的SQL语句
     * @return boolean     true - 写入成功，false - 写入失败！
     */
    private function write($sql)
	{
        $size = strlen($sql);

        //由于压缩原因，无法计算出压缩后的长度，这里假设压缩率为50%，
        //一般情况压缩率都会高于50%；
        $size = $this->config['compress'] ? $size / 2 : $size;

        $this->open($size);
        return $this->config['compress'] ? @gzwrite($this->fp, $sql) : @fwrite($this->fp, $sql);
    }

    /**
     * 备份表结构
     * @param  string  $table 表名
     * @param  integer $start 起始行数
     * @return boolean        false - 备份失败
     */
    public function backup($table, $start)
	{
        //创建DB对象
        $db = Yii::$app->db;

        //备份表结构
        if(0 == $start){
            $result = $db->createCommand("SHOW CREATE TABLE `{$table}`")->queryAll();
            $sql  = "\n";
            $sql .= "-- -----------------------------\n";
            $sql .= "-- Table structure for `{$table}`\n";
            $sql .= "-- -----------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= trim($result[0]['Create Table']) . ";\n\n";
            if(false === $this->write($sql)){
                return false;
            }
        }
		
        //数据总数
        $result = $db->createCommand("SELECT COUNT(*) AS count FROM `{$table}`")->queryAll();
        $count  = $result['0']['count'];

        //备份表数据
        if($count)
		{
            //写入数据注释
            if(0 == $start) {
                $sql  = "-- -----------------------------\n";
                $sql .= "-- Records of `{$table}`\n";
                $sql .= "-- -----------------------------\n";
                $this->write($sql);
            }

            //备份数据记录
            $result = $db->createCommand("SELECT * FROM `{$table}` LIMIT {$start}, 1000")->queryAll();
            foreach ($result as $row) 
			{
                /* null数据单独处理，addslashes会将null处理成'' */
                $row = array_map(function($f){
                    if ($f !== null) {
                        return addslashes($f);
                    }
                }, $row);
                /* 不能使用implode，因为null会被转化为'' */
                $field = '';
                $len = count($row);
                $i = 0;
                foreach ($row as $value) {
                    if($value === null){
                        $field .= "',  NULL";
                    } else {
                        if($i == 0){
                            $field .= "', ''".$value;
                        } else if($i + 1 == $len){
                            $field .= "', '".$value."'";
                        } else {
                            $field .= "', '".$value;
                        }
                    }
                    $i ++;
                }
                $field = substr($field,4);
                $field = str_replace("NULL'","NULL",$field);

                // 如果不加 IGNORE则会出现：Duplicate entry "1" for key "PRIMARY"
                // 当一个语句创建表在#1，插入记录在#2 即：不同属于一个sql文件的情况下就会发生
                //$sql = "INSERT INTO `{$table}` VALUES (" . $field . ");\n";
                $sql = "INSERT IGNORE INTO `{$table}` VALUES (" . $field . ");\n";
                if(false === $this->write($sql)){
                    return false;
                }
            }

            //还有更多数据
            if($count > $start + 1000){
                return array($start + 1000, $count);
            }
        }

        //备份下一表
        return 0;
    }

    public function import($start)
	{
        //还原数据
        $db = Yii::$app->db;

        if($this->config['compress']){
            $gz   = gzopen($this->file[1], 'r');
            $size = 0;
        } else {

            $size = filesize($this->file[1]);
            $gz   = fopen($this->file[1], 'r');
        }

        $sql  = '';
        if($start){
            $this->config['compress'] ? gzseek($gz, $start) : fseek($gz, $start);
        }

        for($i = 0; $i < 1000; $i++){
            $sql .= $this->config['compress'] ? gzgets($gz) : fgets($gz);
            if(preg_match('/.*;$/', trim($sql))){
                if(false !== $db->createCommand($sql)->execute()){
                    $start += strlen($sql);
                } else {
                    return false;
                }
                $sql = '';
            } elseif ($this->config['compress'] ? gzeof($gz) : feof($gz)) {
                return 0;
            }
        }

        return array($start, $size);
    }

    /**
     * 析构方法，用于关闭文件资源
     */
    public function __destruct(){
        $this->config['compress'] ? @gzclose($this->fp) : @fclose($this->fp);
    }
}