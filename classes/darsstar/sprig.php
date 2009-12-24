<?php defined('SYSPATH') or die('No direct script access.');

abstract class Darsstar_Sprig extends Shadowhand_Sprig {

	/**
	 * Load a single record using the current data.
	 *
	 * @param   object   any Database_Query_Builder_Select, NULL for none
	 * @param   integer  number of records to load, FALSE for all
	 * @return  $this
	 */
	public function load(Database_Query_Builder_Select $query = NULL, $limit = 1, array $with = NULL)
	{
		// Load changed values as search parameters
		$changed = $this->changed();

		if ( ! $query)
		{
			$query = DB::select();
		}

		$query->from($this->_table);

		$table = is_array($this->_table) ? $this->_table[1] : $this->_table;

		foreach ($this->_fields as $name => $field)
		{
			if ( ! $field->in_db)
			{
				// Multiple relations cannot be loaded this way
				continue;
			}

			if ($name === $field->column)
			{
				$query->select("{$table}.{$name}");
			}
			else
			{
				$query->select(array("{$table}.{$field->column}", $name));
			}

			if (array_key_exists($name, $changed))
			{
				$query->where("{$table}.{$field->column}", '=', $changed[$name]);
			}
		}

		// Load any specified HasOne or BelongsTo relations
		if ($with != NULL)
		{
			$with = array_unique($with);

			foreach ($with AS $target_path)
			{
				// Split object parts
				$aliases = explode(':', $target_path);
				$target	 = $this;
				foreach ($aliases as $alias)
				{
					// Go down the line of objects to find the given target
					$parent = $target;

					if (! array_key_exists($alias, $target->_fields))
						throw new Sprig_Exception(':name model cannot load with :field, no such field exists',
							array(':name' => get_class($this), ':field' => $alias));

					$field = $parent->_fields[$alias];

					if ( ! ($field instanceof Sprig_Field_ForeignKey) OR
					       ($field instanceof Sprig_Field_HasMany))
						throw new Sprig_Exception(':name model cannot load with :field, it is not the correct relation type',
							array(':name' => get_class($parent), ':field' => $alias));

					$target = Sprig::factory($field->model);

				}
		
				// Target alias is at the end
				$target_alias = $alias;
		
				// Pop-off top alias to get the parent path (user:photo:tag becomes user:photo - the parent table prefix)
				array_pop($aliases);
				$parent_path = implode(':', $aliases);
		
				if (empty($parent_path))
				{
					// Use this table name itself for the parent path
					$parent_path = $table;
				}
				else
				{
					if( ! isset($with[$parent_path]))
					{
						// If the parent path hasn't been joined yet, do it first (otherwise LEFT JOINs fail)
						$with[] = $parent_path;
					}
				}

				$query->join(array($target->_table, $target_path), 'LEFT');
				
				if ($field instanceof Sprig_Field_MPTT_Related)
				{
					if ($field instanceof Sprig_Field_MPTT_Root)
					{
						$query
							->on("{$target_path}.{$target->_left_column}", '=', DB::expr('1'))
							->on("{$target_path}.{$target->_scope_column}", '=', "{$parent_path}.{$parent->_scope_column}");
					}
					elseif ($field instanceof Sprig_Field_MPTT_Parents)
					{
						$query
							->on("{$target_path}.{$this->left_column}", '<', "{$parent_path}.{$this->left_column}")
							->on("{$target_path}.{$this->right_column}", '>', "{$parent_path}.{$this->right_column}")
							->on("{$target_path}.{$this->scope_column}", '=', "{$parent_path}.{$this->scope_column}");

						if ($field instanceof Sprig_Field_MPTT_Parent)
						{
							$query
								->on("{$table}.{$this->level_column}", '=', DB::expr(Database::instance($this->_db)->quote_identifier("{$parent_path}.{$this->level_column}").' - 1'));
						}
						else
						{
							$query
								->order_by("{$target_path}.{$this->left_column}", $direction);
						}
					}
					else
					{
						$target->$alias($query);
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

		if ($limit)
		{
			$query->limit($limit);
		}

		if ($this->_sorting)
		{
			foreach ($this->_sorting as $field => $direction)
			{
				$query->order_by("{$this->_table}.{$field}", $direction);
			}
		}

		if ($limit === 1)
		{
			$result = $query
				->execute($this->_db);

			if (count($result))
			{
				$this->state('loading')->values($result[0])->state('loaded');
			}

			return $this;
		}
		else
		{
			return $query
				->as_object(get_class($this))
				->execute($this->_db);
		}
	}
}