<?php
/**
 * @package    Action log - JoomShopping
 * @version       2.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @сopyright  Copyright (c) 2021 - 2024 Sergey Tolkachyov. All rights reserved.
 * @license       GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Actionlog\Wtjoomshopping\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Component\Jshopping\Site\Lib\JSFactory;
use Joomla\Component\Actionlogs\Administrator\Plugin\ActionLogPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Joomla! Users Actions Logging Plugin.
 *
 * @since  3.9.0
 */
final class Wtjoomshopping extends ActionLogPlugin implements SubscriberInterface
{

	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterSaveCategory'        => 'onAfterSaveCategory',
			'onAfterPublishCategory'     => 'onAfterPublishCategory',
			'onBeforeRemoveCategory'     => 'onBeforeRemoveCategory',
			'onAfterRemoveCategory'      => 'onAfterRemoveCategory',
			'onAfterSaveCategoryImage'   => 'onAfterSaveCategoryImage',
			'onAfterSaveProductEnd'      => 'onAfterSaveProductEnd',
			'onAfterSaveProductImage'    => 'onAfterSaveProductImage',
			'onBeforeRemoveProduct'      => 'onBeforeRemoveProduct',
			'onAfterRemoveProduct'       => 'onAfterRemoveProduct',
			'onAfterPublishProduct'      => 'onAfterPublishProduct',
			'onCopyProductEach'          => 'onCopyProductEach',
			'onAfterSaveProductField'    => 'onAfterSaveProductField',
			'onBeforeRemoveProductField' => 'onBeforeRemoveProductField',
			'onAfterRemoveProductField'  => 'onAfterRemoveProductField',
		];
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

	public function onAfterSaveCategory(Event $event): void
	{
		[$category, $post] = $event->getArguments();

		$context       = $this->getApplication()->getInput()->get('option');
		$user          = $this->getApplication()->getIdentity();
		$lang          = JSFactory::getLang();
		$category_name = $lang->get('name');

		$message = [
			'action'        => 'update',
			'id'            => $user->id,
			'title'         => $user->username,
			'accountlink'   => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'itemlink'      => 'index.php?option=com_jshopping&controller=categories&task=edit&category_id=' . $category->category_id,
			'category_name' => $category->$category_name
		];

		$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_CATEGORY_UPDATE', $context, $user->id);
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
	public function onAfterPublishCategory(Event $event): void
	{
		[$cid, $flag] = $event->getArguments();
		$context       = $this->getApplication()->getInput()->get('option');
		$user          = $this->getApplication()->getIdentity();
		$lang          = JSFactory::getLang();
		$category_name = $lang->get('name');

		$category = JSFactory::getTable('category', 'jshop');

		foreach ($cid as $category_id)
		{
			$category->load($category_id, true);

			$message = [
				'id'             => $user->id,
				'title'          => $user->username,
				'accountlink'    => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'publish_action' => $flag ? Text::_('PLG_WTJOOMSHOPPING_USER_ACTION_PUBLISH') : Text::_('PLG_WTJOOMSHOPPING_USER_ACTION_UNPUBLISH'),
				'itemlink'       => 'index.php?option=com_jshopping&controller=categories&task=edit&category_id=' . $category->category_id,
				'category_name'  => $category->$category_name
			];
			$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_CATEGORY_PUBLISH', $context, $user->id);
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
	public function onBeforeRemoveCategory(Event $event): void
	{
		[$cid] = $event->getArguments();
		$lang          = JSFactory::getLang();
		$category_name = $lang->get('name');
		$category      = JSFactory::getTable('category', 'jshop');
		$category_data = [];
		foreach ($cid as $category_id)
		{
			$category->load($category_id, true);

			$category_data[$category_id] = [
				'category_name' => $category->$category_name
			];
		}
		$this->getApplication()->getSession()->set('actionlog_jshopping_remove_category_data', $category_data);
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
	public function onAfterRemoveCategory(Event $event): void
	{
		[$cid] = $event->getArguments();
		$context       = $this->getApplication()->getInput()->get('option');
		$user          = $this->getApplication()->getIdentity();
		$category_data = $this->getApplication()->getSession()->get('actionlog_jshopping_remove_category_data');
		foreach ($cid as $category_id)
		{

			$message = [
				'id'            => $user->id,
				'title'         => $user->username,
				'accountlink'   => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'category_name' => $category_data[$category_id]['category_name']
			];
			$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_CATEGORY_REMOVE', $context, $user->id);
		}

		$this->getApplication()->getSession()->clear('actionlog_jshopping_remove_category_data');
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
	public function onAfterSaveCategoryImage(Event $event): void
	{
		[$post, $category_image, $path_full, $path_thumb] = $event->getArguments();
		$jshopConfig   = JSFactory::getConfig();
		$context       = $this->getApplication()->getInput()->get('option');
		$user          = $this->getApplication()->getIdentity();
		$lang          = JSFactory::getLang();
		$category_name = $lang->get('name');
		$category      = JSFactory::getTable('category', 'jshop');

		$message = [
			'id'                          => $user->id,
			'title'                       => $user->username,
			'accountlink'                 => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'category_name'               => $category->$category_name,
			'itemlink'                    => 'index.php?option=com_jshopping&controller=categories&task=edit&category_id=' . $category->category_id,
			'jshopping_category_img_path' => $jshopConfig->image_category_live_path . '/' . $category_image
		];
		$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_CATEGORY_IMAGE_SAVE', $context, $user->id);

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
	public function onAfterSaveProductEnd(Event $event): void
	{
		[$product_id] = $event->getArguments();
		$context      = $this->getApplication()->getInput()->get('option');
		$user         = $this->getApplication()->getIdentity();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');
		$product      = JSFactory::getTable('product', 'jshop');
		$product->load($product_id);

		$message = [
			'id'           => $user->id,
			'title'        => $user->username,
			'accountlink'  => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'product_name' => $product->$product_name,
			'itemlink'     => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $product->product_id,
		];
		$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_UPDATE', $context, $user->id);

	}

	/**
	 * @param $product_id JoomShopping product id
	 * @param $name_image JoomShopping product image file name
	 *
	 * @since 1.0.0
	 * @see   \JshoppingModelProducts::save
	 */
	public function onAfterSaveProductImage(Event $event): void
	{
		[$product_id, $name_image] = $event->getArguments();

		$jshopConfig  = JSFactory::getConfig();
		$context      = $this->getApplication()->getInput()->get('option');
		$user         = $this->getApplication()->getIdentity();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');
		$product      = JSFactory::getTable('product', 'jshop');
		$product->load($product_id);

		$message = [
			'id'                         => $user->id,
			'title'                      => $user->username,
			'accountlink'                => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'product_name'               => $product->$product_name,
			'itemlink'                   => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $product->product_id,
			'jshopping_product_img_path' => $jshopConfig->image_product_live_path . '/' . $name_image
		];
		$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_IMAGE_SAVE', $context, $user->id);

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
	public function onBeforeRemoveProduct(Event $event): void
	{
		[$cid] = $event->getArguments();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');
		$product      = JSFactory::getTable('product', 'jshop');
		$product_data = [];
		foreach ($cid as $product_id)
		{
			$product->load($product_id, true);

			$product_data[$product_id] = [
				'product_name' => $product->$product_name
			];
		}
		$this->getApplication()->getSession()->set('actionlog_jshopping_remove_product_data', $product_data);
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
	public function onAfterRemoveProduct(Event $event): void
	{
		[$cid] = $event->getArguments();
		$context      = $this->getApplication()->getInput()->get('option');
		$user         = $this->getApplication()->getIdentity();
		$product_data = $this->getApplication()->getSession()->get('actionlog_jshopping_remove_product_data');
		foreach ($cid as $product_id)
		{

			$message = [
				'id'           => $user->id,
				'title'        => $user->username,
				'accountlink'  => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'product_name' => $product_data[$product_id]['product_name']
			];
			$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_REMOVE', $context, $user->id);
		}

		$this->getApplication()->getSession()->clear('actionlog_jshopping_remove_product_data');
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
	public function onAfterPublishProduct(Event $event): void
	{
		[$cid, $flag] = $event->getArguments();
		$context      = $this->getApplication()->getInput()->get('option');
		$user         = $this->getApplication()->getIdentity();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');

		$product = JSFactory::getTable('product', 'jshop');

		foreach ($cid as $product_id)
		{
			$product->load($product_id, true);

			$message = [
				'id'             => $user->id,
				'title'          => $user->username,
				'accountlink'    => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'publish_action' => $flag ? Text::_('PLG_WTJOOMSHOPPING_USER_ACTION_PUBLISH') : Text::_('PLG_WTJOOMSHOPPING_USER_ACTION_UNPUBLISH'),
				'itemlink'       => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $product->product_id,
				'product_name'   => $product->$product_name
			];
			$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_PUBLISH', $context, $user->id);
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
	public function onCopyProductEach(Event $event): void
	{
		[$cid, $key, $value, $product] = $event->getArguments();
		$context      = $this->getApplication()->getInput()->get('option');
		$user         = $this->getApplication()->getIdentity();
		$lang         = JSFactory::getLang();
		$product_name = $lang->get('name');

		$old_product = JSFactory::getTable('product', 'jshop');

		foreach ($cid as $product_id)
		{
			$old_product->load($product_id, true);

			$message = [
				'id'               => $user->id,
				'title'            => $user->username,
				'accountlink'      => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'olditemlink'      => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $old_product->product_id,
				'old_product_name' => $old_product->$product_name,
				'new_itemlink'     => 'index.php?option=com_jshopping&controller=products&task=edit&product_id=' . $product->product_id,
				'new_product_name' => $product->$product_name
			];
			$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_PRODUCT_COPY', $context, $user->id);
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
	public function onAfterSaveProductField(Event $event): void
	{
		[$productfield] = $event->getArguments();
		$context           = $this->getApplication()->getInput()->get('option');
		$user              = $this->getApplication()->getIdentity();
		$lang              = JSFactory::getLang();
		$productfield_name = $lang->get('name');
		$message = [
			'id'                => $user->id,
			'title'             => $user->username,
			'accountlink'       => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
			'itemlink'          => 'index.php?option=com_jshopping&controller=productfields&task=edit&product_id=' . $productfield->id,
			'product_field_name' => $productfield->$productfield_name
		];
		$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_PRODUCTFIELD_UPDATE', $context, $user->id);
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
	public function onBeforeRemoveProductField(Event $event): void
	{
		[$cid] = $event->getArguments();
		$lang         = JSFactory::getLang();
		$product_field_name = $lang->get('name');
		$product_field      = JSFactory::getTable('productField', 'jshop');
		$product_field_data = [];
		foreach ($cid as $product_field_id)
		{
			$product_field->load($product_field_id, true);

			$product_field_data[$product_field_id] = [
				'product_field_name' => $product_field->$product_field_name
			];
		}
		$this->getApplication()->getSession()->set('actionlog_jshopping_remove_product_field_data', $product_field_data);
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
	public function onAfterRemoveProductField(Event $event): void
	{
		[$cid] = $event->getArguments();
		$context      = $this->getApplication()->getInput()->get('option');
		$user         = $this->getApplication()->getIdentity();
		$product_field_data = $this->getApplication()->getSession()->get('actionlog_jshopping_remove_product_field_data');
		foreach ($cid as $product_field_id)
		{
			$message = [
				'id'           => $user->id,
				'title'        => $user->username,
				'accountlink'  => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
				'product_field_name' => $product_field_data[$product_field_id]['product_field_name']
			];
			$this->addLog([$message], 'PLG_WTJOOMSHOPPING_TYPE_PRODUCTFIELD_REMOVE', $context, $user->id);
		}

		$this->getApplication()->getSession()->clear('actionlog_jshopping_remove_product_field_data');
	}

}
