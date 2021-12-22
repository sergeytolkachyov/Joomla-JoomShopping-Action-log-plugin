<?php
/**
 * @package         Joomla.Plugins
 * @subpackage      System.actionlogs
 *
 * @copyright   (C) 2018 Open Source Matters, Inc. <https://www.joomla.org>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\CMS\Version;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\HTML\HTMLHelper;

JLoader::register('ActionLogPlugin', JPATH_ADMINISTRATOR . '/components/com_actionlogs/libraries/actionlogplugin.php');
JLoader::register('ActionlogsHelper', JPATH_ADMINISTRATOR . '/components/com_actionlogs/helpers/actionlogs.php');

/**
 * Joomla! Users Actions Logging Plugin.
 *
 * @since  3.9.0
 */
class PlgActionlogWtjoomshopping extends ActionLogPlugin
{

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array    $config   An optional associative array of configuration settings.
	 *
	 * @since   3.9.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		// Load JoomShopping config and models
		if (!class_exists('JSFactory'))
		{
			require_once(JPATH_SITE . '/components/com_jshopping/lib/factory.php');
		}

	}

##
#   JoomShopping categories events
##

	/**
	 * After save JoomShopping category logging method
	 * This method adds a record to #__action_logs contains (message, date, context, user)
	 * Method is called right after the category is saved
	 *
	 * @param   array  $category  The context of the content passed to the plugin
	 * @param   array  $post      A JTableContent object
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelCategories::save
	 */

	public function onAfterSaveCategory($category, $post): void
	{


		$context       = $this->app->input->get('option');
		$user          = Factory::getUser();
		$lang          = JSFactory::getLang();
		$category_name = $lang->get('name');

		$message = array(
			'action'        => 'update',
			'id'            => $user->id,
			'title'         => $user->username,
			'accountlink'   => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'itemlink'      => 'index.php?option=com_jshopping&controller=categories&task=edit&category_id=' . $category->category_id,
			'category_name' => $category->$category_name
		);

		$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_CATEGORY_UPDATE', $context, $user->id);
	}


	/**
	 * On after JoomShopping category publish/unpublish
	 *
	 * @param   array   $cid   JoomShopping categories ids array for publush/unpublish action
	 * @param   string  $flag  1 - publish, 0 - unpublish
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelCategories::publish
	 */
	public function onAfterPublishCategory($cid, $flag)
	{
		$context       = $this->app->input->get('option');
		$user          = Factory::getUser();
		$lang          = JSFactory::getLang();
		$category_name = $lang->get('name');

		$category = JSFactory::getTable('category', 'jshop');

		foreach ($cid as $category_id)
		{
			$category->load($category_id, true);

			$message = array(
				'id'             => $user->id,
				'title'          => $user->username,
				'accountlink'    => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'publish_action' => $flag ? Text::_('PLG_WTJOOMSHOPPING_USER_ACTION_PUBLISH') : Text::_('PLG_WTJOOMSHOPPING_USER_ACTION_UNPUBLISH'),
				'itemlink'       => 'index.php?option=com_jshopping&controller=categories&task=edit&category_id=' . $category->category_id,
				'category_name'  => $category->$category_name
			);
			$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_CATEGORY_PUBLISH', $context, $user->id);
		}
	}

	/**
	 * On before JoomShopping remove categories.
	 * Store categories names to session for to get onAfterRemoveCategory event
	 *
	 * @param   array  $cid  JoomShopping categories ids array for removing
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelCategories::deleteList
	 */
	public function onBeforeRemoveCategory($cid)
	{
		$lang          = JSFactory::getLang();
		$category_name = $lang->get('name');
		$category      = JSFactory::getTable('category', 'jshop');
		$category_data = [];
		foreach ($cid as $category_id)
		{
			$category->load($category_id, true);

			$category_data[$category_id] = array(
				'category_name' => $category->$category_name
			);
		}
		Factory::getSession()->set('actionlog_jshopping_remove_category_data', $category_data);
	}


	/**
	 * On after JoomShopping remove categories
	 *
	 * @param   array  $cid  JoomShopping categories ids array for removing
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelCategories::deleteList
	 */
	public function onAfterRemoveCategory($cid)
	{
		$context       = $this->app->input->get('option');
		$user          = Factory::getUser();
		$category_data = Factory::getSession()->get('actionlog_jshopping_remove_category_data');
		foreach ($cid as $category_id)
		{

			$message = array(
				'id'            => $user->id,
				'title'         => $user->username,
				'accountlink'   => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'category_name' => $category_data[$category_id]['category_name']
			);
			$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_CATEGORY_REMOVE', $context, $user->id);
		}

		Factory::getSession()->clear('actionlog_jshopping_remove_category_data');
	}

	/**
	 * On after JoomShopping category save image
	 *
	 * @param   array  $post            array data from view
	 * @param   array  $category_image  category image file name
	 * @param   array  $path_full       full image path from server root
	 * @param   array  $path_thumb      full image thumb path from server root
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelCategories::uploadImage
	 */
	public function onAfterSaveCategoryImage(&$post, &$category_image, &$path_full, &$path_thumb)
	{
		$jshopConfig   = JSFactory::getConfig();
		$context       = $this->app->input->get('option');
		$user          = Factory::getUser();
		$lang          = JSFactory::getLang();
		$category_name = $lang->get('name');
		$category      = JSFactory::getTable('category', 'jshop');

		$message = array(
			'id'                          => $user->id,
			'title'                       => $user->username,
			'accountlink'                 => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'category_name'               => $category->$category_name,
			'itemlink'                    => 'index.php?option=com_jshopping&controller=categories&task=edit&category_id=' . $category->category_id,
			'jshopping_category_img_path' => $jshopConfig->image_category_live_path . '/' . $category_image
		);
		$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_CATEGORY_IMAGE_SAVE', $context, $user->id);

	}


##
#   JoomShopping product events
##

	/**
	 * @param $product_id JoomShopping product id
	 *
	 *
	 * @since 1.0.0
	 * @see   \JshoppingModelProducts::save
	 */
	public function onAfterSaveProductEnd($product_id)
	{
		$jshopConfig  = JSFactory::getConfig();
		$context      = $this->app->input->get('option');
		$user         = Factory::getUser();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');
		$product      = JSFactory::getTable('product', 'jshop');
		$product->load($product_id);

		$message = array(
			'id'           => $user->id,
			'title'        => $user->username,
			'accountlink'  => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'product_name' => $product->$product_name,
			'itemlink'     => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $product->product_id,
			//'jshopping_category_img_path' => $jshopConfig->image_category_live_path . '/' . $category_image
		);
		$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_UPDATE', $context, $user->id);

	}

	/**
	 * @param $product_id JoomShopping product id
	 * @param $name_image JoomShopping product image file name
	 *
	 * @since 1.0.0
	 * @see   \JshoppingModelProducts::save
	 */
	public function onAfterSaveProductImage($product_id, $name_image)
	{
		$jshopConfig  = JSFactory::getConfig();
		$context      = $this->app->input->get('option');
		$user         = Factory::getUser();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');
		$product      = JSFactory::getTable('product', 'jshop');
		$product->load($product_id);

		$message = array(
			'id'                         => $user->id,
			'title'                      => $user->username,
			'accountlink'                => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'product_name'               => $product->$product_name,
			'itemlink'                   => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $product->product_id,
			'jshopping_product_img_path' => $jshopConfig->image_product_live_path . '/' . $name_image
		);
		$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_IMAGE_SAVE', $context, $user->id);

	}

	/**
	 * On before JoomShopping remove products.
	 * Store products names to session for to get onAfterRemoveProduct event
	 *
	 * @param   array  $cid  JoomShopping products ids array for removing
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelProducts::deleteList
	 */
	public function onBeforeRemoveProduct($cid)
	{
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');
		$product      = JSFactory::getTable('product', 'jshop');
		$product_data = [];
		foreach ($cid as $product_id)
		{
			$product->load($product_id, true);

			$product_data[$product_id] = array(
				'product_name' => $product->$product_name
			);
		}
		Factory::getSession()->set('actionlog_jshopping_remove_product_data', $product_data);
	}


	/**
	 * On after JoomShopping remove products
	 *
	 * @param   array  $cid  JoomShopping products ids array for removing
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelProducts::deleteList
	 */
	public function onAfterRemoveProduct($cid)
	{
		$context      = $this->app->input->get('option');
		$user         = Factory::getUser();
		$product_data = Factory::getSession()->get('actionlog_jshopping_remove_product_data');
		foreach ($cid as $product_id)
		{

			$message = array(
				'id'           => $user->id,
				'title'        => $user->username,
				'accountlink'  => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'product_name' => $product_data[$product_id]['product_name']
			);
			$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_REMOVE', $context, $user->id);
		}

		Factory::getSession()->clear('actionlog_jshopping_remove_product_data');
	}

	/**
	 * On after JoomShopping products publish/unpublish
	 *
	 * @param   array   $cid   JoomShopping products ids array for publush/unpublish action
	 * @param   string  $flag  1 - publish, 0 - unpublish
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelProducts::publish
	 */
	public function onAfterPublishProduct($cid, $flag)
	{
		$context      = $this->app->input->get('option');
		$user         = Factory::getUser();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');

		$product = JSFactory::getTable('product', 'jshop');

		foreach ($cid as $product_id)
		{
			$product->load($product_id, true);

			$message = array(
				'id'             => $user->id,
				'title'          => $user->username,
				'accountlink'    => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'publish_action' => $flag ? Text::_('PLG_WTJOOMSHOPPING_USER_ACTION_PUBLISH') : Text::_('PLG_WTJOOMSHOPPING_USER_ACTION_UNPUBLISH'),
				'itemlink'       => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $product->product_id,
				'product_name'   => $product->$product_name
			);
			$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_PUBLISH', $context, $user->id);
		}
	}

	/**
	 * On after JoomShopping products publish/unpublish
	 *
	 * @param   array   $cid   JoomShopping products ids array for publush/unpublish action
	 * @param   string  $flag  1 - publish, 0 - unpublish
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelProducts::publish
	 */
	public function onCopyProductEach(&$cid, &$key, &$value, &$product)
	{
		$context      = $this->app->input->get('option');
		$user         = Factory::getUser();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');

		$old_product = JSFactory::getTable('product', 'jshop');

		foreach ($cid as $product_id)
		{
			$old_product->load($product_id, true);

			$message = array(
				'id'               => $user->id,
				'title'            => $user->username,
				'accountlink'      => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'olditemlink'      => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $old_product->product_id,
				'old_product_name' => $old_product->$product_name,
				'new_itemlink'     => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $product->product_id,
				'new_product_name' => $product->$product_name
			);
			$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_COPY', $context, $user->id);
		}
	}

	/**
	 * on JoomShopping characteristics save
	 * @param object $productfield jshopProductField Object
	 *
	 *
	 * @since 1.0.0
	 * @see \JshoppingModelProductFields::save
	 */
	public function onAfterSaveProductField(&$productfield)
	{
		$context           = $this->app->input->get('option');
		$user              = Factory::getUser();
		$lang              = JSFactory::getLang();
		$productfield_name = $lang->get('name');
		$message           = array(
			'id'                => $user->id,
			'title'             => $user->username,
			'accountlink'       => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'itemlink'          => 'index.php?option=com_jshopping&controller=productfields&task=edit&product_id=' . $productfield->id,
			'product_field_name' => $productfield->$productfield_name
		);
		$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_PRODUCTFIELD_UPDATE', $context, $user->id);
	}


	/**
	 * On before JoomShopping remove characteristics.
	 * Store characteristics names to session for to get onAfterRemoveProductField event
	 *
	 * @param   array  $cid  JoomShopping characteristics ids array for removing
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelProducts::deleteList
	 */
	public function onBeforeRemoveProductField($cid)
	{
		$lang         = JSFactory::getLang();
		$product_field_name = $lang->get('name');
		$product_field      = JSFactory::getTable('productField', 'jshop');
		$product_field_data = [];
		foreach ($cid as $product_field_id)
		{
			$product_field->load($product_field_id, true);

			$product_field_data[$product_field_id] = array(
				'product_field_name' => $product_field->$product_field_name
			);
		}
		Factory::getSession()->set('actionlog_jshopping_remove_product_field_data', $product_field_data);
	}


	/**
	 * On after JoomShopping remove products
	 *
	 * @param   array  $cid  JoomShopping products ids array for removing
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @see     \JshoppingModelProducts::deleteList
	 */
	public function onAfterRemoveProductField($cid)
	{
		$context      = $this->app->input->get('option');
		$user         = Factory::getUser();
		$product_field_data = Factory::getSession()->get('actionlog_jshopping_remove_product_field_data');
		foreach ($cid as $product_field_id)
		{

			$message = array(
				'id'           => $user->id,
				'title'        => $user->username,
				'accountlink'  => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'product_field_name' => $product_field_data[$product_field_id]['product_field_name']
			);
			$this->addLog(array($message), 'PLG_WTJOOMSHOPPING_TYPE_PRODUCTFIELD_REMOVE', $context, $user->id);
		}

		Factory::getSession()->clear('actionlog_jshopping_remove_product_field_data');
	}

}
