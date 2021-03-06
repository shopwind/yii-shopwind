<?php

/**
 * @link https://www.shopwind.net/
 * @copyright Copyright (c) 2018 ShopWind Inc. All Rights Reserved.
 *
 * This is not free software. Do not use it for commercial purposes. 
 * If you need commercial operation, please contact us to purchase a license.
 * @license https://www.shopwind.net/license/
 */

namespace frontend\controllers;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

use common\library\Basewind;
use common\library\Language;
use common\library\Message;
use common\library\Resource;
use common\library\Page;
use common\library\Promotool;

/**
 * @Id Seller_fullfreeController.php 2018.5.22 $
 * @author mosir
 */

class Seller_fullfreeController extends \common\controllers\BaseSellerController
{
	/**
	 * 初始化
	 * @var array $view 当前视图
	 * @var array $params 传递给视图的公共参数
	 */
	public function init()
	{
		parent::init();
		$this->view  = Page::setView('mall');
		$this->params = ArrayHelper::merge($this->params, Page::getAssign('user'));
	}

    public function actionIndex()
    {
		if(!Yii::$app->request->isPost)
		{
			$fullfreeTool = Promotool::getInstance('fullfree')->build(['store_id' => $this->visitor['store_id']]);
			$this->params['fullfree'] = $fullfreeTool->getInfo();
			
			if(($message = $fullfreeTool->checkAvailable(true, false)) !== true) {
				$this->params['tooldisabled'] = $message;
			}
			
			$this->params['_foot_tags'] = Resource::import('jquery.plugins/jquery.form.js');
			
			// 当前位置
			$this->params['_curlocal'] = Page::setLocal(Language::get('seller_fullfree'), Url::toRoute('seller_fullfree/index'), Language::get('fullfree_index'));
		
			// 当前用户中心菜单
			$this->params['_usermenu'] = Page::setMenu('seller_fullfree', 'fullfree_index');

			$this->params['page'] = Page::seo(['title' => Language::get('fullfree_index')]);
        	return $this->render('../seller_fullfree.index.html', $this->params);
		}
		else
		{
			$post = Basewind::trimAll(Yii::$app->request->post('fullfree'), true, ['quantity']);

			$post->status = intval(Yii::$app->request->post('status'));
			$model = new \frontend\models\Seller_fullfreeForm(['store_id' => $this->visitor['store_id']]);
			if(!$model->save($post, true)) {
				return Message::warning($model->errors);
			}			
			return Message::display(Language::get('handle_ok'));
		}		
	}
	
	/* 三级菜单 */
    public function getUserSubmenu()
    {
        $submenus =  array(
            array(
                'name' => 'fullfree_index',
                'url'  => Url::toRoute(['seller_fullfree/index']),
            )
        );

        return $submenus;
    }
}