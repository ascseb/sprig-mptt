<?php defined('SYSPATH') or die('No direct script access.');

abstract class Darsstar_Sprig extends Shadowhand_Sprig {

	// With calls already applied
	protected $_with_applied = array();

	public function __set($name, $value)
	{
		if ( ! $this->_init)
		{
			// The constructor must always be called first
			$this->__construct();

			// This object is about to be loaded by mysql_fetch_object() or similar
			$this->state('loading');
		}

		if ( ! isset($this->_fields[$name]))
		{
			if ($this->state() === 'loading' AND strpos($name, ':'))
			{
				list($name, $f_field) = explode(':', $name, 2);

				if (array_key_exists($name, $this->_fields) AND $this->_fields[$name] instanceof Sprig_Field_MPTT_Related)
				{
					$field = $this->_fields[$name];

					if ( ! array_key_exists($name, $this->_related))
					{
						$this->_related[$name] = Sprig::factory($field->model);
					}

					$this->_related[$name]->state('loading')->$f_field = $value;
					$this->_related[$name]->state('loaded');
					return;
				}

				$name .= ':'.$f_field;
			}
		}

		parent::__set($name, $value);
	}

	protected function _with($query, $target_path)
	{
		// Get the database instance
		$db = $this->_db instanceof Database ? $this->_db : Database::instance($this->_db);

		$table = is_array($this->_table) ? $this->_table[1] : $this->_table;

		// Split object parts
		$aliases = explode(':', $target_path);
		$target	 = $this;
		foreach ($aliases as & $alias)
		{
			// Go down the line of objects to find the given target
			$parent = $target;

			if ($parent instanceof Sprig_MPTT AND in_array($alias, array('ancestors', 'descendants', 'children', 'leaves', 'siblings')))
			{
				$alias = Inflector::singular($alias);
			}

			if ( ! array_key_exists($alias, $target->_fields))
				throw new Sprig_Exception(':name model cannot load with :field, no such field exists',
					array(':name' => get_class($this), ':field' => $alias));

			$field = $parent->_fields[$alias];

			if ( ! $field instanceof Sprig_Field_ForeignKey
			    OR ($field instanceof Sprig_Field_HasMany
				    AND ! $field instanceof Sprig_Field_MPTT_Related))
				throw new Sprig_Exception(':name model cannot load with :field, it is not the correct relation type',
					array(':name' => get_class($parent), ':field' => $alias));

			$target = Sprig::factory($field->model);

		}

		// Target alias is at the end
		$target_alias = $alias;

		// Reset target_path
		$target_path = implode(':', $aliases);

		// Pop-off top alias to get the parent path (user:photo:tag becomes user:photo - the parent table prefix)
		array_pop($aliases);
		$parent_path = $original_parent_path = implode(':', $aliases);

		if (empty($parent_path))
		{
			// Use this table name itself for the parent path
			$parent_path = $table;
		}
		else
		{
			if( ! isset($this->_with_applied[$parent_path]))
			{
				// If the parent path hasn't been joined yet, do it first (otherwise LEFT JOINs fail)
				$this->with($parent_path);
			}
		}

		// Add to with_applied to prevent duplicate joins
		$this->_with_applied[$target_path] = TRUE;

		$query->join(array($target->_table, $target_path), 'LEFT');
		
		if ($field instanceof Sprig_Field_MPTT_Related)
		{
			if ($field instanceof Sprig_Field_MPTT_Root)
			{
				$query
					->on("{$target_path}.{$target->_scope_column}", '=', "{$parent_path}.{$parent->_scope_column}")
					->where("{$target_path}.{$target->_left_column}", '=', 1);
			}
			elseif ($field instanceof Sprig_Field_MPTT_Ancestors)
			{
				$query
					->on("{$target_path}.{$target->left_column}", '<', "{$parent_path}.{$parent->left_column}")
					->on("{$target_path}.{$target->right_column}", '>', "{$parent_path}.{$parent->right_column}")
					->on("{$target_path}.{$target->scope_column}", '=', "{$parent_path}.{$parent->scope_column}");

				if ($field instanceof Sprig_Field_MPTT_Parent)
				{
					$query
						->on("{$target_path}.{$target->level_column}", '+ 1 =', "{$parent_path}.{$parent->level_column}");
				}
				else
				{
					if ( ! $field->root)
					{
						$query->where("{$target_path}.{$target->left_column}", '<>', 1);
					}	

					$query
						->order_by("{$target_path}.{$target->left_column}", $field->direction);
				}
			}
			elseif ($field instanceof Sprig_Field_MPTT_Descendants)
			{
				$left_operator  = $field->self ? '>=' : '>';
				$right_operator = $field->self ? '<=' : '<';

				$query
					->on("{$target_path}.{$target->left_column}",  $left_operator,  "{$parent_path}.{$parent->left_column}")
					->on("{$target_path}.{$target->right_column}", $right_operator, "{$parent_path}.{$parent->right_column}");

				if ($field instanceof Sprig_Field_MPTT_Children)
				{
					if ($field instanceof Sprig_Field_MPTT_First_Child)
					{
						$query
							->on("{$target_path}.{$target->left_column}", '+ 1 =', "{$parent_path}.{$parent->left_column}");
					}
					elseif ($field instanceof Sprig_Field_MPTT_Last_Child)
					{
						$query
							->on("{$target_path}.{$target->right_column}", '+ 1 =', "{$parent_path}.{$parent->right_column}");
					}
					else
					{
						$level_operator = $field->self ? '<= 1 +' : '= 1 +';

						$query
							->on("{$target_path}.{$target->level_column}", $level_operator, "{$parent_path}.{$parent->level_column}");
					}

					if ($field instanceof Sprig_Field_MPTT_Leaves)
					{
						$query
							->on("{$target_path}.{$target->left_column}", '+ 1 =', "{$target_path}.{$target->right_column}");
					}
				}

				$query
					->on("{$target_path}.{$target->scope_column}", '=', "{$parent_path}.{$parent->scope_column}")
					->order_by("{$target_path}.{$target->left_column}", $field->direction);
			}
			elseif ($field instanceof Sprig_Field_MPTT_Siblings)
			{
				if ( ! empty($original_parent_path))
				{
					$original_parent_path .= ':';
				}

				if (isset($this->_with_applied[$original_parent_path.'parent']))
				{
					$left  = "{$original_parent_path}parent.{$this->left_column}";
					$right = "{$original_parent_path}parent.{$this->right_column}";
				}
				else
				{
					$left = clone $right = DB::select()
						->from(array($target->_table, "sub:{$target_path}"))
						->where("sub:{$target_path}.{$target->left_column}",  '<', DB::expr($db->quote_identifier("{$parent_path}.{$target->left_column}")))
						->where("sub:{$target_path}.{$target->right_column}", '>', DB::expr($db->quote_identifier("{$parent_path}.{$target->right_column}")))
						->where("sub:{$target_path}.{$target->scope_column}", '=', DB::expr($db->quote_identifier("{$parent_path}.{$target->scope_column}")))
						->where("sub:{$target_path}.{$target->level_column}", '+ 1 =', DB::expr($db->quote_identifier("{$parent_path}.{$target->level_column}")));

					$left->select("sub:{$target_path}.{$target->left_column}");
					$right->select("sub:{$target_path}.{$target->right_column}");
				}

				$query
					->on("{$target_path}.{$target->left_column}",  '>', $left)
					->on("{$target_path}.{$target->right_column}", '<', $right)
					->on("{$target_path}.{$target->scope_column}", '=', "{$parent_path}.{$parent->scope_column}")
					->on("{$target_path}.{$target->level_column}", '=', "{$parent_path}.{$parent->level_column}");

				if ( ! $field->self)
				{
					$query
						->where($target->pk($target_path), '<>', DB::expr($db->quote_identifier($parent->pk($parent_path))));
				}

				$query
					->order_by("{$target_path}.{$target->left_column}", $field->direction);
			}
		}
		elseif ($field instanceof Sprig_Field_HasOne)
		{
			$query->on("{$parent_path}.{$this->_primary_key}", '=', "{$target_path}.{$field->column}");
		}
		else
		{
			$query->on("{$parent_path}.{$field->column}", '=', $target->pk($target_path));
		}

		foreach ($target->_fields as $f_name => $f_field)
		{
			if ($f_field instanceof Sprig_Field_ForeignKey OR ! $f_field->in_db)
				continue;
			
			$query->select(array("{$target_path}.{$f_field->column}", "{$target_path}:{$f_name}"));
		}
	}
}