<?php defined('SYSPATH') or die('No direct script access.');

class Darsstar_Sprig_Field_MPTT_Siblings extends Sprig_Field_HasMany implements Sprig_Field_MPTT_Related {

	public $self = FALSE;

	public $direction = 'ASC';

}