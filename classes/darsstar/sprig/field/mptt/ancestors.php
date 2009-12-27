<?php defined('SYSPATH') or die('No direct script access.');

class Darsstar_Sprig_Field_MPTT_Ancestors extends Sprig_Field_HasMany implements Sprig_Field_MPTT_Related {

	public $root = TRUE;

	public $direction = 'ASC';

}