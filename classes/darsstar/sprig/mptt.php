<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Modified Preorder Tree Traversal Class.
 * 
 * Ported from Sprig_MPTT originally by Matthew Davies and Kiall Mac Innes
 *
 * @package Sprig_MPTT
 * @author Mathew Davies
 * @author Kiall Mac Innes
 * @author Paul Banks
 */
abstract class Darsstar_Sprig_MPTT extends Sprig
{
	/**
	 * @access public
	 * @var string left column name.
	 */
	public $left_column = NULL;
	
	/**
	 * @access public
	 * @var string right column name.
	 */
	public $right_column = NULL;
	
	/**
	 * @access public
	 * @var string level column name.
	 */
	public $level_column = NULL;
	
	/**
	 * @access public
	 * @var string scope column name.
	 **/
	public $scope_column = NULL;
	
	/**
	 * Initialize the fields and add MPTT field defaults if not specified
	 * @return void
	 */
	protected function __construct()
	{
		// Initialize sprig (this will call _init() in the model)
		parent::__construct();

		// Check we don't have a composite primary Key
		if (is_array($this->pk())) 
		{
			throw new Sprig_Exception('Sprig_MPTT does not support composite primary keys');
		}

		// Check Sprig MPTT fields exist, if not add defaults
		foreach ($this->_fields as $name => $field)
		{
			// Check the field extends Sprig_Field_MPTT
			if ($field instanceof Sprig_Field_MPTT)
			{
				if ($field instanceof Sprig_Field_MPTT_Left)
				{
					$this->left_column = $name;
				}
				elseif ($field instanceof Sprig_Field_MPTT_Right)
				{
					$this->right_column = $name;
				}
				elseif ($field instanceof Sprig_Field_MPTT_Level)
				{
					$this->level_column = $name;
				}
				elseif ($field instanceof Sprig_Field_MPTT_Scope)
				{
					$this->scope_column = $name;
				}
			}
		}

		// If any of the MPTT fields havn't been defined, create defaults
		if (is_null($this->left_column))
		{
			$this->left_column = 'lft';
			$this->_fields['lft'] = new Sprig_Field_MPTT_Left(array('column' => 'lft'));
		}
		if (is_null($this->right_column))
		{
			$this->right_column = 'rgt';
			$this->_fields['rgt'] = new Sprig_Field_MPTT_Right(array('column' => 'rgt'));;
		}
		if (is_null($this->level_column))
		{
			$this->level_column = 'lvl';
			$this->_fields['lvl'] = new Sprig_Field_MPTT_Level(array('column' => 'lvl'));;
		}
		if (is_null($this->scope_column))
		{
			$this->scope_column = 'scope';
			$this->_fields['scope'] = new Sprig_Field_MPTT_Scope(array('column' => 'scope'));;
		}

		// Set all related fields so we can 'with' them
		$related = array(
			'root',
			'parent',
			'parents',
			'children',
			'first_child',
			'last_child',
			'descendants',
			'siblings',
			'leaves'
		);

		foreach ($related as $field)
		{
			$sprig_field = 'Sprig_Field_MPTT_'.ucwords($field);
			$this->_fields[$field] = new $sprig_field(array(
				'model' => $this->_model,
			));
		}

		// Check we have default values for all (MPTT) fields (otherwise we cause errors)
		foreach ($this->_fields as $name => $field)
		{
			if ($field instanceof Sprig_Field_MPTT AND ! isset($this->_original[$name]))
			{
				$this->_original[$name] = NULL;
			}
		}
	}

	/**
	 * Locks table.
	 *
	 * @access private
	 */
	protected function lock()
	{
		Database::instance($this->_db)->query(NULL, 'LOCK TABLE '.$this->_table.' WRITE', TRUE);
	}
	
	/**
	 * Unlock table.
	 *
	 * @access private
	 */
	protected function unlock()
	{
		Database::instance($this->_db)->query(NULL, 'UNLOCK TABLES', TRUE);
	}

	/**
	 * Does the current node have children?
	 *
	 * @access public
	 * @return bool
	 */
	public function has_children()
	{
		return (($this->{$this->right_column} - $this->{$this->left_column}) > 1);
	}
	
	/**
	 * Is the current node a leaf node?
	 *
	 * @access public
	 * @return bool
	 */
	public function is_leaf()
	{
		return ! $this->has_children();
	}
	
	/**
	 * Is the current node a descendant of the supplied node.
	 *
	 * @access public
	 * @param Sprig_MPTT $target Target
	 * @return bool
	 */
	public function is_descendant($target)
	{
		return (
					$this->{$this->left_column} > $target->{$this->left_column} 
					AND $this->{$this->right_column} < $target->{$this->right_column} 
					AND $this->{$this->scope_column} = $target->{$this->scope_column}
				);
	}
	
	/**
	 * Is the current node a direct child of the supplied node?
	 *
	 * @access public
	 * @param Sprig_MPTT $target Target
	 * @return bool
	 */
	public function is_child($target)
	{
		return ($this->parent->{$this->pk()} === $target->{$this->pk()});
	}
	
	/**
	 * Is the current node the direct parent of the supplied node?
	 *
	 * @access public
	 * @param Sprig_MPTT $target Target
	 * @return bool
	 */
	public function is_parent($target)
	{
		return ($this->{$this->pk()} === $target->parent->{$this->pk()});
	}
	
	/**
	 * Is the current node a sibling of the supplied node
	 *
	 * @access public
	 * @param Sprig_MPTT $target Target
	 * @return bool
	 */
	public function is_sibling($target)
	{
		if ($this->{$this->pk()} === $target->{$this->pk()})
			return FALSE;
		
		return ($this->parent->{$this->pk()} === $target->parent->{$this->pk()});
	}
	
	/**
	 * Is the current node a root node?
	 *
	 * @access public
	 * @return bool
	 */
	public function is_root()
	{
		return ($this->{$this->left_column} === 1);
	}

	/**
	 * Returns the root node.
	 *
	 * @access protected
	 * @return Sprig_MPTT/FALSE on invalid scope
	 */
	public function root( & $query, $scope = NULL)
	{
		$table = is_array($this->_table) ? $this->_table[1] : $this->_table;

		$query = $query instanceof Database_Query_Builder_Select ? $query : DB::select();

		if ($scope === NULL AND $this->_loaded)
		{
			$scope = $this->{$this->scope_column};
		}
		elseif ($scope === NULL AND ! $this->_loaded)
		{
			return FALSE;
		}
		
		$query
			->where("{$table}.{$this->left_column}", '=', 1)
			->where("{$table}.{$this->scope_column}", '=', $scope);

		return $this;
	}
	
	/**
	 * Returns the parent of the current node.
	 *
	 * @access public
	 * @return Sprig_MPTT
	 */
	public function parent( & $query)
	{
		return $this->parents($query, TRUE, 'ASC', TRUE);
	}
	
	/**
	 * Returns the parents of the current node.
	 *
	 * @access public
	 * @param bool $root include the root node?
	 * @param string $direction direction to order the left column by.
	 * @return Sprig_MPTT
	 */
	public function parents( & $query, $root = TRUE, $direction = 'ASC', $direct_parent_only = FALSE)
	{
		$table = is_array($this->_table) ? $this->_table[1] : $this->_table;

		$query = $query instanceof Database_Query_Builder_Select ? $query : DB::select();

		$query
			->where("{$table}.{$this->left_column}", '<', $this->{$this->left_column})
			->where("{$table}.{$this->right_column}", '>', $this->{$this->right_column})
			->where("{$table}.{$this->scope_column}", '=', $this->{$this->scope_column})
			->order_by("{$table}.{$this->left_column}", $direction);
			
		if ( ! $root)
		{
			$query->where("{$table}.{$this->left_column}", '!=', 1);
		}	
		
		if ($direct_parent_only)
		{
			$query->where("{$table}.{$this->level_column}", '=', $this->{$this->level_column} - 1);
			$query->limit(1);
		}
		
		return $this;
	}

	/**
	 * Returns the children of the current node.
	 *
	 * @access public
	 * @param bool $self include the current loaded node?
	 * @param string $direction direction to order the left column by.
	 * @return Sprig_MPTT
	 */
	public function children( & $query, $self = FALSE, $direction = 'ASC', $limit = FALSE)
	{
		return $this->descendants($query, $self, $direction, TRUE, FALSE, $limit);
	}

	/**
	 * Returns the descendants of the current node.
	 *
	 * @access public
	 * @param bool $self include the current loaded node?
	 * @param string $direction direction to order the left column by.
	 * @return Sprig_MPTT
	 */
	public function descendants( & $query, $self = FALSE, $direction = 'ASC', $direct_children_only = FALSE, $leaves_only = FALSE, $limit = FALSE)
	{
		$table = is_array($this->_table) ? $this->_table[1] : $this->_table;

		$left_operator = $self ? '>=' : '>';
		$right_operator = $self ? '<=' : '<';

		$query = $query instanceof Database_Query_Builder_Select ? $query : DB::select();

		$query
			->where("{$table}.{$this->left_column}", $left_operator, $this->{$this->left_column})
			->where("{$table}.{$this->right_column}", $right_operator, $this->{$this->right_column})
			->where("{$table}.{$this->scope_column}", '=', $this->{$this->scope_column})
			->order_by("{$table}.{$this->left_column}", $direction);
		
		if ($direct_children_only)
		{
			if ($self)
			{
				$query
					->and_where_open()
					->where("{$table}.{$this->level_column}", '=', $this->{$this->level_column})
					->or_where("{$table}.{$this->level_column}", '=', $this->{$this->level_column} + 1)
					->and_where_close();
			}
			else
			{
				$query->where("{$table}.{$this->level_column}", '=', $this->{$this->level_column} + 1);
			}
		}

		if ($leaves_only)
		{
			$db = Database::instance($this->_db);
			$query->where("{$table}.{$this->right_column}", '=', DB::expr($db->quote_identifier("{$table}.{$this->left_column}").' + 1'));
		}

		if ($limit)
		{
			$query->limit($limit);
		}

		return $this;
	}
	
	/**
	 * Returns the siblings of the current node
	 *
	 * @access public
	 * @param bool $self include the current loaded node?
	 * @param string $direction direction to order the left column by.
	 * @return Sprig_MPTT
	 */
	public function siblings( & $query, $self = FALSE, $direction = 'ASC')
	{
		$table = is_array($this->_table) ? $this->_table[1] : $this->_table;

		$query = $query instanceof Database_Query_Builder_Select ? $query : DB::select();

		$query
			->where("{$table}.{$this->left_column}", '>', $this->parent->{$this->left_column})
			->where("{$table}.{$this->right_column}", '<', $this->parent->{$this->right_column})
			->where("{$table}.{$this->scope_column}", '=', $this->{$this->scope_column})
			->where("{$table}.{$this->level_column}", '=', $this->{$this->level_column})
			->order_by("{$table}.{$this->left_column}", $direction);
		
		if ( ! $self)
		{
			$query->where($this->pk($table), '<>', $this->{$this->pk()});
		}
		
		return $this;
	}
	
	/**
	 * Returns leaves under the current node.
	 *
	 * @access public
	 * @return Sprig_MPTT
	 */
	public function leaves( & $query, $self = FALSE, $direction = 'ASC')
	{
		return $this->descendants($query, $self, $direction, TRUE, TRUE);
	}
	
	/**
	 * Get Size
	 *
	 * @access protected
	 * @return integer
	 */
	protected function get_size()
	{
		return ($this->{$this->right_column} - $this->{$this->left_column}) + 1;
	}

	/**
	 * Create a gap in the tree to make room for a new node
	 *
	 * @access private
	 * @param integer $start start position.
	 * @param integer $size the size of the gap (default is 2).
	 */
	private function create_space($start, $size = 2)
	{
		// Update the left values, then the right.
		DB::update($this->_table)
			->set(array($this->left_column => new Database_Expression('`'.$this->left_column.'` + '.$size)))
			->where($this->left_column, '>=', $start)
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->execute($this->_db);
			
		DB::update($this->_table)
			->set(array($this->right_column => new Database_Expression('`'.$this->right_column.'` + '.$size)))
			->where($this->right_column, '>=', $start)
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->execute($this->_db);
	}
	
	/**
	 * Closes a gap in a tree. Mainly used after a node has
	 * been removed.
	 *
	 * @access private
	 * @param integer $start start position.
	 * @param integer $size the size of the gap (default is 2).
	 */
	private function delete_space($start, $size = 2)
	{
		// Update the left values, then the right.
		DB::update($this->_table)
			->set(array($this->left_column => new Database_Expression('`'.$this->left_column.'` - '.$size)))
			->where($this->left_column, '>=', $start)
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->execute($this->_db);
			
		DB::update($this->_table)
			->set(array($this->right_column => new Database_Expression('`'.$this->right_column.'` - '.$size)))
			->where($this->right_column, '>=', $start)
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->execute($this->_db);
	}
	
	/**
	 * Insert this object as the root of a new scope
	 * 
	 * Other object fields must be set in the normal Sprig way 
	 * otherwise validation exception will be thrown
	 *
	 * @param integer $scope New scope to create.
	 * @return Sprig_MPTT
	 * @throws Validation_Exception on invalid $additional_fields data
	 **/
	public function insert_as_new_root($scope = 1)
	{	
		// Make sure the specified scope doesn't already exist.
		$root = $this->root($scope);

		if ($root->loaded())
			return FALSE;
		
		// Create a new root node in the new scope.
		$this->{$this->left_column} = 1;
		$this->{$this->right_column} = 2;
		$this->{$this->level_column} = 0;
		$this->{$this->scope_column} = $scope;
		
		try
		{
			parent::create();
		}
		catch (Validate_Exception $e)
		{
			// There was an error validating the additional fields, re-thow it
			throw $e;
		}
		
		return $this;
	}
	
	/**
	 * Insert the object
	 * 
	 * Sprig_MPTT|mixed $target target node primary key value or Sprig_MPTT object. 
	 * @param string $copy_left_from target object property to take new left value from
	 * @param integer $left_offset offset for left value
	 * @param integer $level_offset offset for level value
	 * @access protected
	 * @return Sprig_MPTT
	 * @throws Validation_Exception
	 */
	
	protected function insert($target, $copy_left_from, $left_offset, $level_offset)
	{
		// Insert should only work on new nodes.. if its already it the tree it needs to be moved!
		if ($this->_loaded)
			return FALSE;
		
		
		
		if ( ! $target instanceof $this)
		{
			$target = Sprig_MPTT::factory($this->_model, array($this->pk() => $target))->load();
			
			if ( ! $target->loaded())
			{
				return FALSE;
			}
		}
		else
		{
			$target->reload();
		}
		
		$this->lock();
		
		$this->{$this->left_column}  = $target->{$copy_left_from} + $left_offset;
		$this->{$this->right_column} = $this->{$this->left_column} + 1;
		$this->{$this->level_column} = $target->{$this->level_column} + $level_offset;
		$this->{$this->scope_column} = $target->{$this->scope_column};
		
		$this->create_space($this->{$this->left_column});
		
		try
		{
			parent::create();
		}
		catch (Exception $e)
		{
			// We had a problem creating - make sure we clean up the tree
			$this->delete_space($this->{$this->left_column});
			$this->unlock();
			throw $e;
		}
		
		$this->unlock();
		
		return $this;
	}
	
	/**
	 * Inserts a new node as the first child of the target node
	 *
	 * @access public
	 * @param Sprig_MPTT|mixed $target target node primary key value or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function insert_as_first_child($target)
	{
		return $this->insert($target, $this->left_column, 1, 1);
	}
	
	/**
	 * Inserts a new node as the last child of the target node
	 *
	 * @access public
	 * @param Sprig_MPTT|mixed $target target node primary key value or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function insert_as_last_child($target)
	{
		return $this->insert($target, $this->right_column, 0, 1);
	}

	/**
	 * Inserts a new node as a previous sibling of the target node.
	 *
	 * @access public
	 * @param Sprig_MPTT|integer $target target node id or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function insert_as_prev_sibling($target)
	{
		return $this->insert($target, $this->left_column, 0, 0);
	}

	/**
	 * Inserts a new node as the next sibling of the target node.
	 *
	 * @access public
	 * @param Sprig_MPTT|integer $target target node id or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function insert_as_next_sibling($target)
	{	
		return $this->insert($target, $this->right_column, 1, 0);
	}
	
	/**
	 * Overloaded create method
	 * 
	 * @access public
	 * @return Sprig_MPTT|bool 
	 * @throws Validation_Exception
	 */
	public function create()
	{
		// Don't allow creation directly as it will invalidate the tree
		throw new Sprig_Exception('You cannot use create() on Sprig_MPTT model :name. Use an appropriate insert_* method instead',
				array(':name' => get_class($this)));
	}
	
	/**
	 * Removes a node and it's descendants.
	 *
	 * @access public
	 */
	public function delete(Database_Query_Builder_Delete $query = NULL)
	{
		if ($query !== NULL)
		{
			throw new Sprig_Exception('Sprig_MPTT does not support passing a query object to delete()');
		}
		
		$this->lock();
		
		// Handle un-foreseen exceptions
		try
		{
			DB::delete($this->_table)
				->where($this->left_column, '>=', $this->{$this->left_column})
				->where($this->right_column, '<=', $this->{$this->right_column})
				->where($this->scope_column, '=', $this->{$this->scope_column})
				->execute($this->_db);
			
			$this->delete_space($this->{$this->left_column}, $this->get_size());
		}
		catch (Exception $e)
		{
			//Unlock table and re-throw exception
			$this->unlock();
			throw $e;
		}
		
		$this->unlock();
	}

	/**
	 * Overloads the select_list method to
	 * support indenting.
	 * 
	 * Returns all recods in the current scope
	 *
	 * @param string $key first table column.
	 * @param string $val second table column.
	 * @param string $indent character used for indenting.
	 * @return array 
	 */
	public function select_list($key = 'id', $value = 'name', $indent = NULL)
	{
		$result = DB::select($key, $value, $this->level_column)
			->from($this->_table)
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->order_by($this->left_column, 'ASC')
			->execute($this->_db);
			
		if (is_string($indent))
		{		
			$array = array();
			
			foreach ($result as $row)
			{
				$array[$row[$key]] = str_repeat($indent, $row[$this->level_column]).$row[$value];
			}
			
			return $array;
		}

		return $result->as_array($key, $value);
	}
	
	/**
	 * Move to First Child
	 *
	 * Moves the current node to the first child of the target node.
	 *
	 * @param Sprig_MPTT|integer $target target node id or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function move_to_first_child($target)
	{
		return $this->move($target, TRUE, 1, 1, TRUE);
	}
	
	/**
	 * Move to Last Child
	 *
	 * Moves the current node to the last child of the target node.
	 *
	 * @param Sprig_MPTT|integer $target target node id or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function move_to_last_child($target)
	{	
		return $this->move($target, FALSE, 0, 1, TRUE);
	}
	
	/**
	 * Move to Previous Sibling.
	 *
	 * Moves the current node to the previous sibling of the target node.
	 *
	 * @param Sprig_MPTT|integer $target target node id or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function move_to_prev_sibling($target)
	{	
		return $this->move($target, TRUE, 0, 0, FALSE);
	}
	
	/**
	 * Move to Next Sibling.
	 *
	 * Moves the current node to the next sibling of the target node.
	 *
	 * @param Sprig_MPTT|integer $target target node id or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function move_to_next_sibling($target)
	{
		return $this->move($target, FALSE, 1, 0, FALSE);
	}
	
	/**
	 * Move
	 *
	 * @param Sprig_MPTT|integer $target target node id or Sprig_MPTT object.
	 * @param bool $left_column use the left column or right column from target
	 * @param integer $left_offset left value for the new node position.
	 * @param integer $level_offset level
	 * @param bool allow this movement to be allowed on the root node
	 */
	protected function move($target, $left_column, $left_offset, $level_offset, $allow_root_target)
	{
		if ( ! $this->loaded())
			return FALSE;
		
		// Make sure we have the most upto date version of this AFTER we lock
		$this->lock();
		$this->reload();
		
		// Catch any database or other excpetions and unlock
		try
		{
			if ( ! $target instanceof $this)
			{
				$target = Sprig_MPTT::factory($this->_model, array($this->pk() => $target))->load();

				if ( ! $target->loaded())
				{
					$this->unlock();
					return FALSE;
				}
			}
			else
			{
				$target->reload();
			}

			// Stop $this being moved into a descendant or itself or disallow if target is root
			if ($target->is_descendant($this) 
				OR $this->{$this->pk()} === $target->{$this->pk()}
				OR ($allow_root_target === FALSE AND $target->is_root()))
			{
				$this->unlock();
				return FALSE;
			}

			$left_offset = ($left_column === TRUE ? $target->{$this->left_column} : $target->{$this->right_column}) + $left_offset;
			$level_offset = $target->{$this->level_column} - $this->{$this->level_column} + $level_offset;
	
			$size = $this->get_size();

			$this->create_space($left_offset, $size);
	
			// if node is moved to a position in the tree "above" its current placement
			// then its lft/rgt may have been altered by create_space
			$this->reload();

			$offset = ($left_offset - $this->{$this->left_column});

			$db = Database::instance($this->_db);

			// Update the values.
			DB::update($this->_table)
				->set(array(
					$this->left_column	=> $this->{$this->left_column}	+ $offset,
					$this->right_column	=> $this->{$this->right_column}	+ $offset,
					$this->level_column	=> $this->{$this->level_column}	+ $level_offset,
					$this->scope_column	=> $target->{$this->scope_column}
				))
				->where($this->left_column, '>=', $this->{$this->left_column})
				->where($this->right_column, '<=',$this->{$this->right_column})
				->where($this->scope_column, '=', $this->{$this->scope_column});

			$this->delete_space($this->{$this->left_column}, $size);
		}
		catch (Exception $e)
		{
			//Unlock table and re-throw exception
			$this->unlock();
			throw $e;
		}

		$this->unlock();

		return $this;
	}
	
	/**
	 *
	 * @access public
	 * @param $column - Which field to get.
	 * @return mixed
	 */
	public function __get($name)
	{
		if ( ! $this->_init)
		{
			// The constructor must always be called first
			$this->__construct();

			// This object is about to be loaded by mysql_fetch_object() or similar
			$this->state('loading');
		}

		if (isset($this->_related[$name]))
		{
			// Shortcut to any related object
			return $this->_related[$name];
		}

		switch ($name)
		{
			case 'parent':
				return Sprig::factory($this->_model)->parent($query)->load($query);
			case 'parents':
				return Sprig::factory($this->_model)->parents($query)->load($query, FALSE);
			case 'children':
				return Sprig::factory($this->_model)->children($children)->load($query, FALSE);
			case 'first_child':
				return Sprig::factory($this->_model)->children($query, FALSE, 'ASC')->load($query);
			case 'last_child':
				return Sprig::factory($this->_model)->children($query, FALSE, 'DESC')->load($query);
			case 'siblings':
				return Sprig::factory($this->_model)->siblings($query)->load($query, FALSE);
			case 'root':
				return Sprig::factory($this->_model)->root($query)->load($query);
			case 'leaves':
				return Sprig::factory($this->_model)->leaves($query)->load($query, FALSE);
			case 'descendants':
				return Sprig::factory($this->_model)->descendants($query)->load($query, FALSE);
			default:
				return parent::__get($name);
		}
	}
	
	/**
	 * Force object to reload MPTT fields from database
	 * 
	 * @return $this
	 */
	public function reload()
	{
		if ( ! $this->_loaded) 
		{
			return FALSE;
		}

		$mptt_vals = DB::select(
				$this->left_column,
				$this->right_column,
				$this->level_column,
				$this->scope_column
			)
			->from($this->_table)
			->where($this->pk(), '=', $this->{$this->pk()})
			->execute($this->_db)
			->current();

		return $this->values($mptt_vals);
	}

}