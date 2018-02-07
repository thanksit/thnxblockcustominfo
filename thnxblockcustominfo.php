<?php
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
require_once _PS_MODULE_DIR_.'thnxblockcustominfo/classes/CustomInfoBlock.php';
class thnxblockcustominfo extends Module implements WidgetInterface
{
	public $html = '';
	public function __construct()
	{
		$this->name = 'thnxblockcustominfo';
		$this->tab = 'front_office_features';
		$this->version = '1.0.0';
		$this->author = 'thanksit.com';
		$this->bootstrap = true;
		$this->need_instance = 0;
		parent::__construct();
		$this->displayName = $this->l('Platinum Custom info block');
		$this->description = $this->l('Adds information in your store.');
		$this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
	}
	public function install()
	{
		if(!parent::install()
			|| !$this->installDB()
			|| !$this->installFixtures()
			|| !$this->registerHook('displayTopColumn')
			)
			return false;
		else
			return true;
	}
	public function installDB()
	{
		$return = true;
		$return &= Db::getInstance()->execute('
				CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'thnxinfo` (
				`id_info` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`id_shop` int(10) unsigned DEFAULT NULL,
				PRIMARY KEY (`id_info`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;'
		);
		$return &= Db::getInstance()->execute('
				CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'thnxinfo_lang` (
				`id_info` INT UNSIGNED NOT NULL,
				`id_lang` int(10) unsigned NOT NULL ,
				`text` text NOT NULL,
				PRIMARY KEY (`id_info`, `id_lang`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;'
		);
		return $return;
	}
	public function uninstall()
	{
		return parent::uninstall() && $this->uninstallDB();
	}
	public function uninstallDB($drop_table = true)
	{
		$ret = true;
		if($drop_table)
			$ret &=  Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'thnxinfo`') && Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'thnxinfo_lang`');

		return $ret;
	}
	public function getContent()
	{
		$id_info = (int)Tools::getValue('id_info');
		if (Tools::isSubmit('savethnxblockcustominfo'))
		{
			if ($this->processSaveCmsInfo())
				return $this->html . $this->renderList();
			else
				return $this->html . $this->renderForm();
		}
		elseif (Tools::isSubmit('updatethnxblockcustominfo') || Tools::isSubmit('addthnxblockcustominfo'))
		{
			$this->html .= $this->renderForm();
			return $this->html;
		}
		else if (Tools::isSubmit('deletethnxblockcustominfo'))
		{
			$info = new CustomInfoBlock((int)$id_info);
			$info->delete();
			$this->_clearCache('thnxblockcustominfo.tpl');
			Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
		}
		else
		{
			$this->html .= $this->renderList();
			return $this->html;
		}
	}
	public function processSaveCmsInfo()
	{
		if ($id_info = Tools::getValue('id_info')){
			$info = new CustomInfoBlock((int)$id_info);
		}
		else
		{
			$info = new CustomInfoBlock();
			if (Shop::isFeatureActive())
			{
				$shop_ids = Tools::getValue('checkBoxShopAsso_configuration');
				if (!$shop_ids)
				{
					$this->html .= '<div class="alert alert-danger conf error">'.$this->l('You have to select at least one shop.').'</div>';
					return false;
				}
			}
			else{
				$info->id_shop = Shop::getContextShopID();
			}
		}
		$languages = Language::getLanguages(false);
		$text = array();
		foreach ($languages AS $lang){
			$text[$lang['id_lang']] = Tools::getValue('text_'.$lang['id_lang']);
		}
		$info->text = $text;
		if (Shop::isFeatureActive() && !$info->id_shop)
		{
			$saved = true;
			foreach ($shop_ids as $id_shop)
			{
				$info->id_shop = $id_shop;
				$saved &= $info->add();
			}
		}
		else{
			$saved = $info->save();
		}
		if (!$saved){
			$this->html .= '<div class="alert alert-danger conf error">'.$this->l('An error occurred while attempting to save.').'</div>';
		}
		return $saved;
	}
	protected function renderForm()
	{
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		$fields_form = array(
			'tinymce' => true,
			'legend' => array(
				'title' => $this->l('New custom CMS block'),
			),
			'input' => array(
				'id_info' => array(
					'type' => 'hidden',
					'name' => 'id_info'
				),
				'content' => array(
					'type' => 'textarea',
					'label' => $this->l('Text'),
					'lang' => true,
					'name' => 'text',
					'cols' => 40,
					'rows' => 10,
					'class' => 'rte',
					'autoload_rte' => true,
				),
			),
			'submit' => array(
				'title' => $this->l('Save'),
			),
			'buttons' => array(
				array(
					'href' => AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
					'title' => $this->l('Back to list'),
					'icon' => 'process-icon-back'
				)
			)
		);
		if (Shop::isFeatureActive() && Tools::getValue('id_info') == false)
		{
			$fields_form['input'][] = array(
				'type' => 'shop',
				'label' => $this->l('Shop association'),
				'name' => 'checkBoxShopAsso_theme'
			);
		}
		$helper = new HelperForm();
		$helper->module = $this;
		$helper->name_controller = 'thnxblockcustominfo';
		$helper->identifier = $this->identifier;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		foreach (Language::getLanguages(false) as $lang)
			$helper->languages[] = array(
				'id_lang' => $lang['id_lang'],
				'iso_code' => $lang['iso_code'],
				'name' => $lang['name'],
				'is_default' => ($default_lang == $lang['id_lang'] ? 1 : 0)
			);
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;
		$helper->toolbar_scroll = true;
		$helper->title = $this->displayName;
		$helper->submit_action = 'savethnxblockcustominfo';
		$helper->fields_value = $this->getFormValues();
		return $helper->generateForm(array(array('form' => $fields_form)));
	}
	protected function renderList()
	{
		$this->fields_list = array();
		$this->fields_list['id_info'] = array(
				'title' => $this->l('Block ID'),
				'type' => 'text',
				'search' => false,
				'orderby' => false,
			);
		if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP)
			$this->fields_list['shop_name'] = array(
					'title' => $this->l('Shop'),
					'type' => 'text',
					'search' => false,
					'orderby' => false,
				);
		$this->fields_list['text'] = array(
				'title' => $this->l('Block text'),
				'type' => 'text',
				'search' => false,
				'orderby' => false,
			);
		$helper = new HelperList();
		$helper->shopLinkType = '';
		$helper->simple_header = false;
		$helper->identifier = 'id_info';
		$helper->actions = array('edit', 'delete');
		$helper->show_toolbar = true;
		$helper->imageType = 'jpg';
		$helper->toolbar_btn['new'] = array(
			'href' => AdminController::$currentIndex.'&configure='.$this->name.'&add'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
			'desc' => $this->l('Add new')
		);
		$helper->title = $this->displayName;
		$helper->table = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$content = $this->getListContent($this->context->language->id);
		return $helper->generateList($content, $this->fields_list);
	}
	protected function getListContent($id_lang = null)
	{
		if (is_null($id_lang))
			$id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		$sql = 'SELECT r.`id_info`, rl.`text`, s.`name` as shop_name
			FROM `'._DB_PREFIX_.'thnxinfo` r
			LEFT JOIN `'._DB_PREFIX_.'thnxinfo_lang` rl ON (r.`id_info` = rl.`id_info`)
			LEFT JOIN `'._DB_PREFIX_.'shop` s ON (r.`id_shop` = s.`id_shop`)
			WHERE `id_lang` = '.(int)$id_lang.' AND (';
		if ($shop_ids = Shop::getContextListShopID())
			foreach ($shop_ids as $id_shop)
				$sql .= ' r.`id_shop` = '.(int)$id_shop.' OR ';
		$sql .= ' r.`id_shop` = 0 )';
		$content = Db::getInstance()->executeS($sql);
		foreach ($content as $key => $value)
			$content[$key]['text'] = substr(strip_tags($value['text']), 0, 200);
		return $content;
	}
	public function getFormValues()
	{
		$fields_value = array();
		$id_info = (int)Tools::getValue('id_info');
		foreach (Language::getLanguages(false) as $lang)
			if ($id_info)
			{
				$info = new CustomInfoBlock((int)$id_info);
				$fields_value['text'][(int)$lang['id_lang']] = $info->text[(int)$lang['id_lang']];
			}
			else
				$fields_value['text'][(int)$lang['id_lang']] = Tools::getValue('text_'.(int)$lang['id_lang'], '');
		$fields_value['id_info'] = $id_info;
		return $fields_value;
	}
	public function getInfos($id_lang, $id_shop)
	{
		$sql = 'SELECT r.`id_info`, r.`id_shop`, rl.`text`
			FROM `'._DB_PREFIX_.'thnxinfo` r
			LEFT JOIN `'._DB_PREFIX_.'thnxinfo_lang` rl ON (r.`id_info` = rl.`id_info`)
			WHERE `id_lang` = '.(int)$id_lang.' AND  `id_shop` = '.(int)$id_shop;

		return Db::getInstance()->executeS($sql);
	}
	public function installFixtures()
	{
		$return = true;
		$tab_texts = array(
			array(
				'text' => '<p class="t_align_c tt_uppercase f_size_16 m_top_20">This is a Custom CMS block edited from to this module admin panel. You can insert any content (text, images, HTML) here.</p>'
			),
		);
		$shops_ids = Shop::getShops(true, null, true);
		$return = true;
		foreach ($tab_texts as $tab)
		{
			$info = new CustomInfoBlock();
			foreach (Language::getLanguages(false) as $lang)
				$info->text[$lang['id_lang']] = $tab['text'];
			foreach ($shops_ids as $id_shop)
			{
				$info->id_shop = $id_shop;
				$return &= $info->add();
			}
		}
		return $return;
	}
	public function renderWidget($hookName = null, array $configuration = [])
	{
	    $this->smarty->assign($this->getWidgetVariables($hookName,$configuration));
	    return $this->fetch('module:'.$this->name.'/views/templates/front/'.$this->name.'.tpl');	
	}
	public function getWidgetVariables($hookName = null, array $configuration = [])
	{
		$return_arr = array();
	    $infos = $this->getInfos($this->context->language->id, $this->context->shop->id);
		$return_arr['thnxinfos'] = $infos;
		$return_arr['nbblocks'] = count($infos);
		return $return_arr;
	}
}