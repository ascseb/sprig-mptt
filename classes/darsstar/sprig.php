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

					if (! ($field instanceof Sprig_Field_MPTT_Related) OR
					    ! ($field instanceof Sprig_Field_ForeignKey) OR
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
					$target->table($target_path);

					if (in_array($alias, array('first_child', 'last_child')))
					{
						$order = $alias === 'first_child' ? 'ASC' : 'DESC';
						$target->children($query, FALSE, $order);
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