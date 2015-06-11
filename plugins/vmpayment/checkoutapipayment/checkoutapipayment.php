<?php
defined('_JEXEC') or die('Restricted access');
include 'autoload.php';
if (!class_exists('Creditcard')) {
    require_once(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'creditcard.php');
}
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}
class plgVmpaymentCheckoutApipayment extends Model
{

}