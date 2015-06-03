<?php
// $HeadURL: https://joomgallery.org/svn/joomgallery/JG-2.0/Plugins/JoomMarketLink/trunk/joommarketlink.php $
// $Id: joommarketlink.php 3756 2012-04-29 16:45:29Z chraneco $
/****************************************************************************************\
**   JoomMarketLink plugin 3.0                                                          **
**   By: JoomGallery::ProjectTeam                                                       **
**   Copyright (C) 2012 - 2015  JoomGallery::ProjectTeam                                **
**   Based on: JoomGallery 1.0.0 by JoomGallery::ProjectTeam                            **
**   Released under GNU GPL Public License                                              **
**   License: http://www.gnu.org/copyleft/gpl.html or have a look                       **
**   at administrator/components/com_joomgallery/LICENSE.TXT                            **
\****************************************************************************************/

defined('_JEXEC') or die;

/**
 * Plugin for linking images to market products
 *
 * @package JoomGallery
 * @since   1.0
 */
class plgJoomGalleryJoomMarketLink extends JPlugin
{
  /**
   * Holds default data for icon and link of several market extensions
   *
   * @var array
   */
  protected $marketdata = null;

  /**
   * Constructor
   *
   * @param   object  $subject  The object to observe
   * @param   array   $config   An array that holds the plugin configuration
   * @return  void
   * @since   1.0
   */
  public function __construct(&$subject, $config)
  {
    parent::__construct($subject, $config);
    $this->loadLanguage();

    $this->marketdata = array('virtuemart'    => array( 'icon'  => 'components/com_virtuemart/assets/images/vmgeneral/menu_icon.png',
                                                        'link'  => 'index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=%s'),
                              'hikashop'      => array( 'icon'  => 'media/com_hikashop/images/icons/icon-16-hikashop.png',
                                                        'link'  => 'index.php?option=com_hikashop&view=product&layout=show&product_id=%s'),
                              'joomshopping'  => array( 'icon'  => 'media/joomgallery/images/basket.png',
                                                        'link'  => 'index.php?option=com_jshopping&controller=cart&task=add&category_id=1&product_id=%s'),
                              'other'         => array( 'icon'  => '',
                                                        'link'  => '')
                              );
  }

  /**
   * onJoomDisplayIcon event
   * Method is called by the view
   *
   * @param   string  $context  Context in which the event was triggered
   * @param   object  $image    Image data of the image displayed
   * @return  string  HTML code which will be placed inside the icon bar of JoomGallery's images
   * @since   1.0
   */
  function onJoomDisplayIcons($context, $image)
  {
    if(is_object($image) && isset($image->id))
    {
      $image = $image->id;
    }

    // Load the additional data from the database
    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
          ->select('details_value')
          ->from(_JOOM_TABLE_IMAGE_DETAILS)
          ->where('id = '.(int) $image)
          ->where('details_key = '.$db->q('marketlink.productid'));
    $db->setQuery($query);
    $result = $db->loadresult();

    // Check for a database error
    if($db->getErrorNum())
    {
      $this->_subject->setError($db->getErrorMsg());

      return false;
    }

    // Create the output
    $html = '';
    if($result)
    {
      if(!$icon = $this->params->get('icon'))
      {
        $icon = $this->marketdata[$this->params->get('market', 'other')]['icon'];
      }
      if(!$link = $this->params->get('link'))
      {
        $link = $this->marketdata[$this->params->get('market', 'other')]['link'];
      }
      $html = '<a href="'.JRoute::_(sprintf($link, $result)).'"'.JHtml::_('joomgallery.tip', 'PLG_JOOMGALLERY_JOOMMARKETLINK_TIPTEXT', 'PLG_JOOMGALLERY_JOOMMARKETLINK_TIPCAPTION', true).'>
      <img src="'.$icon.'" class="jg_icon jg-marketlink-icon" alt="'.JText::_('PLG_JOOMGALLERY_JOOMMARKETLINK_TIPCAPTION').'" /></a>';
    }

    return $html;
  }

  /**
   * onContentPrepareForm event
   * Method is called after the form was instantiated
   *
   * @param   object  $form The form to be altered
   * @param   array   $data The associated data for the form
   * @return  boolean True on success, false otherwise
   * @since   1.0
   */
  function onContentPrepareForm($form, $data)
  {
    if(!($form instanceof JForm))
    {
      $this->_subject->setError('JERROR_NOT_A_FORM');

      return false;
    }

    // Check we are manipulating a valid form
    $name = $form->getName();
    if(!in_array($name, array(_JOOM_OPTION.'.image', _JOOM_OPTION.'.edit')))
    {
      return true;
    }

    // Add the registration fields to the form
    JForm::addFormPath(dirname(__FILE__));
    $form->loadFile('marketlink', false);

    // Adapt the form for a specific market extension
    switch($this->params->get('market'))
    {
      case 'virtuemart':
        JForm::addFieldPath(JPATH_ROOT.'/administrator/components/com_virtuemart/models/fields');
        $form->setFieldAttribute('productid', 'type', 'product', 'marketlink');
        break;
      case 'hikashop':
        JForm::addFieldPath(JPATH_ROOT.'/components/com_hikashop/fields');
        $form->setFieldAttribute('productid', 'type', 'selectproduct', 'marketlink');
        break;
      default:
        // No changes, a simple text field will be used
        break;
    }

    return true;
  }

  /**
   * onContentPrepareData event
   * Method is called when data is retrieved for preparing a form
   *
   * @param   string  $context  The context for the data
   * @param   object  $data     The image data object
   * @return  void
   * @since   1.0
   */
  function onContentPrepareData($context, $data)
  {
    // Check if we are manipulating a valid form
    if(!in_array($context, array(_JOOM_OPTION.'.image', _JOOM_OPTION.'.edit')))
    {
      return true;
    }

    if(is_object($data) && !isset($data->marketlink) && isset($data->id) && $data->id)
    {
      // Load the profile data from the database.
      $db = JFactory::getDbo();
      $query = $db->getQuery(true)
            ->select('details_value')
            ->from(_JOOM_TABLE_IMAGE_DETAILS)
            ->where('id = '.(int) $data->id)
            ->where('details_key = '.$db->q('marketlink.productid'));
      $db->setQuery($query);
      $result = $db->loadResult();

      // Check for a database error.
      if($db->getErrorNum())
      {
        $this->_subject->setError($db->getErrorMsg());

        return false;
      }

      // Merge the profile data
      $data->marketlink = array('productid' => $result);
    }

    return true;
  }

  /**
   * onContentAfterSave event
   * Method is called after an image was stored successfully
   *
   * @param   string  $context  The context of the store action
   * @param   object  $table    The table object which was used for storing the image
   * @param   boolean $isNew    Determines wether it is a new image which was stored
   * @return  void
   * @since   1.0
   */
  function onContentAfterSave($context, &$table, $isNew)
  {
    if(isset($table->id) && $table->id && in_array($context, array(_JOOM_OPTION.'.image')))
    {
      try
      {
        // At first delete the old entries
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
              ->delete(_JOOM_TABLE_IMAGE_DETAILS)
              ->where('id = '.(int) $table->id)
              ->where('details_key LIKE '.$db->q('marketlink.%'));
        $db->setQuery($query);

        if(!$db->query())
        {
          throw new Exception($db->getErrorMsg());
        }

        $tuples = array();
        $order  = 1;

        // Get the new data and store it
        $data = JRequest::getVar('marketlink', array(), 'post', 'array');
        if($data && isset($data['productid']))
        {
          $productid = (string) $data['productid'];
          $query->clear()
                ->insert(_JOOM_TABLE_IMAGE_DETAILS)
                ->values((int) $table->id.','.$db->q('marketlink.productid').','.$db->q($productid).',0');
          $db->setQuery($query);

          if(!$db->query())
          {
            throw new Exception($db->getErrorMsg());
          }
        }
      }
      catch(Exception $e)
      {
        $this->_subject->setError($e->getMessage());

        return false;
      }
    }

    return true;
  }

  /**
   * Removes all additional image data for the given image id
   *
   * Method is called after an image is deleted from the database
   *
   * @param   string  $context  The context of the delete action
   * @param   object  $table    The table object which was used for deleting the image
   * @return  void
   * @since   1.0
   */
  function onContentAfterDelete($context, $table)
  {
    if(isset($table->id) && $table->id)
    {
      try
      {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
              ->delete(_JOOM_TABLE_IMAGE_DETAILS)
              ->where('id = '.(int) $table->id)
              ->where('details_key LIKE '.$db->q('marketlink.%'));
        $db->setQuery($query);

        if(!$db->query())
        {
          throw new Exception($db->getErrorMsg());
        }
      }
      catch(Exception $e)
      {
        $this->_subject->setError($e->getMessage());

        return false;
      }
    }

    return true;
  }

  /**
   * onJoomGetImageDetailsPrefix event
   *
   * Not used yet. Could be called by maintenance functions for detecting
   * used prefixes in details database table in order to clean it up
   *
   * @return  array An array of used prefixes in details table
   * @since   1.0
   */
  public function onJoomGetImageDetailsPrefixes()
  {
    return array('marketlink');
  }
}
