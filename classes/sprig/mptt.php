<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Modified Preorder Tree Traversal Class.
 * 
 * Ported from ORM_MPTT originally by Matthew Davies and Kiall Mac Innes
 *
 * @package MPTT
 * @author Mathew Davies
 * @author Kiall Mac Innes
 * @author Paul Banks
 */
abstract class Sprig_MPTT extends Sprig
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
	 * @access protected
	 * @var string mptt view folder.
	 */
	protected $_directory = 'mptt';
	
	/**
	 * @access protected
	 * @var string default view folder.
	 */
	protected $_style = 'default';
	
	/**
	 * Initialize the fields and add MPTT field defaults if not specified
	 * @return void
	 */
	public function init()
	{
		if ($this->_init)
		{
			// Can only be called once
			return;
		}
		// Set up Sprig object
		parent::init();
		
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
	}
	

	/**
	 * Locks table.
	 *
	 * @access private
	 */
	protected function lock()
	{
		Database::instance()->query(NULL, 'LOCK TABLE '.$this->_table.' WRITE', TRUE);
	}
	
	/**
	 * Unlock table.
	 *
	 * @access private
	 */
	protected function unlock()
	{
		Database::instance()->query(NULL, 'UNLOCK TABLES', TRUE);
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
	 * @param ORM_MPTT $target Target
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
	 * @param ORM_MPTT $target Target
	 * @return bool
	 */
	public function is_child($target)
	{
		return ($this->parent->{$this->primary_key} === $target->{$this->primary_key});
	}
	
	/**
	 * Is the current node the direct parent of the supplied node?
	 *
	 * @access public
	 * @param ORM_MPTT $target Target
	 * @return bool
	 */
	public function is_parent($target)
	{
		return ($this->{$this->primary_key} === $target->parent->{$this->primary_key});
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
		if ($this->{$this->primary_key} === $target->{$this->primary_key})
			return FALSE;
		
		return ($this->parent->{$this->primary_key} === $target->parent->{$this->primary_key});
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
	public function root($scope = NULL)
	{
		if ($scope === NULL AND $this->_loaded)
		{
			$scope = $this->{$this->scope_column};
		}
		elseif ($scope === NULL AND ! $this->_loaded)
		{
			return FALSE;
		}
		
		return Sprig_MPTT::factory($this->_model, array($this->left_column => 1, $this->scope_column => $scope))->load();
	}
	
	/**
	 * Returns the parent of the current node.
	 *
	 * @access public
	 * @return Sprig_MPTT
	 */
	public function parent()
	{
		return $this->parents(TRUE, 'ASC', TRUE);
	}
	
	/**
	 * Returns the parents of the current node.
	 *
	 * @access public
	 * @param bool $root include the root node?
	 * @param string $direction direction to order the left column by.
	 * @return Sprig_MPTT
	 */
	public function parents($root = TRUE, $direction = 'ASC', $direct_parent_only = FALSE)
	{
		$query = DB::select()
			->where($this->left_column, '<=', $this->{$this->left_column})
			->where($this->right_column, '>=', $this->{$this->right_column})
			->where($this->pk(), '<>', $this->{$this->pk()})
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->order_by($this->left_column, $direction);
			
		if ( ! $root)
		{
			$query->where($this->left_column, '!=', 1);
		}	
		
		$limit = FALSE;
		
		if ($direct_parent_only)
		{
			$query->where($this->level_column, '=', $this->{$this->level_column} - 1);
			$limit = 1;
		}
		
		$parents =  Sprig_MPTT::factory($this->_model)->load($query, $limit);

		
		return $parents;
	}
	
	/**
	 * Returns the children of the current node.
	 *
	 * @access public
	 * @param bool $self include the current loaded node?
	 * @param string $direction direction to order the left column by.
	 * @return Sprig_MPTT
	 */
	public function children($self = FALSE, $direction = 'ASC', $limit = FALSE)
	{
		return $this->descendants($self, $direction, TRUE, FALSE, $limit);
	}
	
	/**
	 * Returns the descendants of the current node.
	 *
	 * @access public
	 * @param bool $self include the current loaded node?
	 * @param string $direction direction to order the left column by.
	 * @return Sprig_MPTT
	 */
	public function descendants($self = FALSE, $direction = 'ASC', $direct_children_only = FALSE, $leaves_only = FALSE, $limit = FALSE)
	{		
		$left_operator = $self ? '>=' : '>';
		$right_operator = $self ? '<=' : '<';
		
		$query = DB::select()
			->where($this->left_column, $left_operator, $this->{$this->left_column})
			->where($this->right_column, $right_operator, $this->{$this->right_column})
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->order_by($this->left_column, $direction);
		
		if ($direct_children_only)
		{
			if ($self)
			{
				$query
					->and_where_open()
					->where($this->level_column, '=', $this->{$this->level_column})
					->or_where($this->level_column, '=', $this->{$this->level_column} + 1)
					->and_where_close();
			}
			else
			{
				$query->where($this->level_column, '=', $this->{$this->level_column} + 1);
			}
		}
		
		return Sprig_MPTT::factory($this->_model)->load($query, $limit);
	}
	
	/**
	 * Returns the siblings of the current node
	 *
	 * @access public
	 * @param bool $self include the current loaded node?
	 * @param string $direction direction to order the left column by.
	 * @return Sprig_MPTT
	 */
	public function siblings($self = FALSE, $direction = 'ASC')
	{	
		$query = DB::select()
			->where($this->left_column, '>', $this->parent->{$this->left_column})
			->where($this->right_column, '<', $this->parent->{$this->right_column})
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->where($this->level_column, '=', $this->{$this->level_column})
			->order_by($this->left_column, $direction);
		
		if ( ! $self)
		{
			$query->where($this->pk(), '<>', $this->{$this->pk()});
		}
		
		return Sprig_MPTT::factory($this->_model)->load($query, FALSE);
	}
	
	/**
	 * Returns leaves under the current node.
	 *
	 * @access public
	 * @return Sprig_MPTT
	 */
	public function leaves($self = FALSE, $direction = 'ASC')
	{
		return $this->descendants($self, $direction, FALSE, TRUE);
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
			->execute();
			
		DB::update($this->_table)
			->set(array($this->right_column => new Database_Expression('`'.$this->right_column.'` + '.$size)))
			->where($this->right_column, '>=', $start)
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->execute();
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
			->execute();
			
		DB::update($this->_table)
			->set(array($this->right_column => new Database_Expression('`'.$this->right_column.'` - '.$size)))
			->where($this->right_column, '>=', $start)
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->execute();
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
		
		$this->lock();
		
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
		
		$this->{$this->left_column}  = $target->{$copy_left_from} + $left_offset;
		$this->{$this->right_column} = $this->{$this->left_column} + 1;
		$this->{$this->level_column} = $target->{$this->level_column} + $level_offset;
		$this->{$this->scope_column} = $target->{$this->scope_column};
		
		$this->create_space($this->{$this->left_column});
		
		try
		{
			parent::create();
		}
		catch (Validate_Exception $e)
		{
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
	public function delete()
	{
		$this->lock();

		DB::delete($this->_table)
			->where($this->left_column, '>=', $this->{$this->left_column})
			->where($this->right_column, '<=', $this->{$this->right_column})
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->execute();
		
		$this->delete_space($this->{$this->left_column}, $this->get_size());
		
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
	 * @param Sprig_MPTT|mixed $target target node primary key value or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function move_to_first_child($target)
	{
		$this->lock();	
		
		// Move should only work on nodes that are already in the tree.. if its not already it the tree it needs to be inserted!
		if (!$this->_loaded)
			return FALSE;

		// Make sure we have the most uptodate version of this AFTER we lock
		$this->reload(); // This should *probably* go into $this->lock();
		
		if ( ! $target instanceof $this)
		{
			$target = Sprig_MPTT::factory($this->_model, array($this->pk() => $target))->load();
			if ( ! $target->loaded())
			{
				return FALSE;
			}
		}
		
		// Stop $this being moved into a descendant or itself
		if ($target->is_descendant($this) OR $this->{$this->pk()} === $target->{$this->pk()})
		{
			$this->unlock();
			return FALSE;
		}
		
		$new_left = $target->{$this->left_column} + 1;
		$level_offset = $target->{$this->level_column} - $this->{$this->level_column} + 1;
		$this->move($new_left, $level_offset, $target->{$this->scope_column});
		$this->unlock();

		return $this;
	}
	
	/**
	 * Move to Last Child
	 *
	 * Moves the current node to the last child of the target node.
	 *
	 * @param Sprig_MPTT|mixed $target target node primary key value or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function move_to_last_child($target)
	{
		// Move should only work on nodes that are already in the tree.. if its not already it the tree it needs to be inserted!
		if (!$this->_loaded)
			return FALSE;
			
		$this->lock();
		$this->reload(); // Make sure we have the most upto date version of this AFTER we lock
		
		if ( ! $target instanceof $this)
		{
			$target = Sprig_MPTT::factory($this->_model, array($this->pk() => $target))->load();
			if ( ! $target->loaded())
			{
				return FALSE;
			}
		}
		
		// Stop $this being moved into a descendant or itself
		if ($target->is_descendant($this) OR $this->{$this->pk()} === $target->{$this->pk()})
		{
			$this->unlock();
			return FALSE;
		}
		
		$new_left = $target->{$this->right_column};
		$level_offset = $target->{$this->level_column} - $this->{$this->level_column} + 1;
		$this->move($new_left, $level_offset, $target->{$this->scope_column});
		$this->unlock();
		
		return $this;
	}
	
	/**
	 * Move to Previous Sibling.
	 *
	 * Moves the current node to the previous sibling of the target node.
	 *
	 * @param Sprig_MPTT|mixed $target target node primary key value or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function move_to_prev_sibling($target)
	{
		// Move should only work on nodes that are already in the tree.. if its not already it the tree it needs to be inserted!
		if (!$this->_loaded)
			return FALSE;

		$this->lock();
		$this->reload(); // Make sure we have the most upto date version of this AFTER we lock
		
		if ( ! $target instanceof $this)
		{
			$target = Sprig_MPTT::factory($this->_model, array($this->pk() => $target))->load();
			if ( ! $target->loaded())
			{
				return FALSE;
			}
		}
		
		// Stop $this being moved into a descendant or itself
		if ($target->is_descendant($this) OR $this->{$this->pk()} === $target->{$this->pk()})
		{
			$this->unlock();
			return FALSE;
		}
		
		$new_left = $target->{$this->left_column};
		$level_offset = $target->{$this->level_column} - $this->{$this->level_column};
		$this->move($new_left, $level_offset, $target->{$this->scope_column});
		$this->unlock();
		
		return $this;
	}
	
	/**
	 * Move to Next Sibling.
	 *
	 * Moves the current node to the next sibling of the target node.
	 *
	 * @param Sprig_MPTT|mixed $target target node primary key value or Sprig_MPTT object.
	 * @return Sprig_MPTT
	 */
	public function move_to_next_sibling($target)
	{
		// Move should only work on nodes that are already in the tree.. if its not already it the tree it needs to be inserted!
		if (!$this->_loaded)
			return FALSE;

		$this->lock();
		$this->reload(); // Make sure we have the most upto date version of this AFTER we lock
		
		if ( ! $target instanceof $this)
		{
			$target = Sprig_MPTT::factory($this->_model, array($this->pk() => $target))->load();
			if ( ! $target->loaded())
			{
				return FALSE;
			}
		}
		
		// Stop $this being moved into a descendant or itself
		if ($target->is_descendant($this) OR $this->{$this->pk()} === $target->{$this->pk()})
		{
			$this->unlock();
			return FALSE;
		}
		
		$new_left = $target->{$this->right_column} + 1;
		$level_offset = $target->{$this->level_column} - $this->{$this->level_column};
		$this->move($new_left, $level_offset, $target->{$this->scope_column});
		$this->unlock();
		
		return $this;
	}
	
	/**
	 * Move
	 *
	 * @param integer $new_left left value for the new node position.
	 * @param integer $level_offset
	 */
	protected function move($new_left, $level_offset, $new_scope)
	{
		$this->lock();

		$size = $this->get_size();
		
		$this->create_space($new_left, $size);

		$this->reload();
		
		$offset = ($new_left - $this->{$this->left_column});
		
		// Update the values.
		
		DB::update($this->_table)
			->set(array(
				$this->left_column => new Database_Expression('`'.$this->left_column.'` + '.$offset),
				$this->right_column => new Database_Expression('`'.$this->right_column.'` + '.$offset),
				$this->level_column => new Database_Expression('`'.$this->level_column.'` + '.$level_offset),
				$this->scope_column => $new_scope
			))
			->where($this->left_column, '>=', $this->{$this->left_column})
			->where($this->right_column, '<=', $this->{$this->right_column})
			->where($this->scope_column, '=', $this->{$this->scope_column})
			->execute();
			
		$this->delete_space($this->{$this->left_column}, $size);

		$this->unlock();
		
		//reload this model to reflect new position
		$this->reload();
	}
	
	/**
	 *
	 * @access public
	 * @param $column - Which field to get.
	 * @return mixed
	 */
	public function __get($column)
	{
		switch ($column)
		{
			case 'parent':
				return $this->parent();
			case 'parents':
				return $this->parents();
			case 'children':
				return $this->children();
			case 'first_child':
				return $this->children(FALSE, 'ASC', 1);
			case 'last_child':
				return $this->children(FALSE, 'DESC', 1);
			case 'siblings':
				return $this->siblings();
			case 'root':
				return $this->root();
			case 'leaves':
				return $this->leaves();
			case 'descendants':
				return $this->descendants();
			default:
				return parent::__get($column);
		}
	}
	
	/**
	 * Verify the tree is in good order 
	 * 
	 * This functions speed is irrelevant - its really only for debugging and unit tests
	 * 
	 * @todo Look for any nodes no longer contained by the root node.
	 * @todo Ensure every node has a path to the root via ->parents(); 
	 * @access public
	 * @return boolean
	 */
	public function verify_tree()
	{
		foreach ($this->get_scopes() as $scope)
		{
			if ( ! $this->verify_scope($scope->scope))
				return FALSE;
		}
		return TRUE;
	}
	
	private function get_scopes()
	{
		// TODO... redo this so its proper :P and open it public
		// used by verify_tree()
		return DB::select()->distinct($this->scope_column)->from($this->_table)->execute();
	}
	
	public function verify_scope($scope)
	{
		$root = $this->root($scope);
		
		$end = $root->{$this->right_column};
		
		// Find nodes that have slipped out of bounds.
		$result = Database::instance()->query(Database::SELECT, 'SELECT count(*) as count FROM `'.$this->_table.'` 
			WHERE `'.$this->scope_column.'` = '.$root->scope.' AND (`'.$this->left_column.'` > '.$end.' 
			OR `'.$this->right_column.'` > '.$end.')', TRUE);
		if ($result[0]->count > 0)
			return FALSE;
		
		// Find nodes that have the same left and right value
		$result = Database::instance()->query(Database::SELECT, 'SELECT count(*) as count FROM `'.$this->_table.'` 
			WHERE `'.$this->scope_column.'` = '.$root->scope.' 
			AND `'.$this->left_column.'` = `'.$this->right_column.'`', TRUE);
		if ($result[0]->count > 0)
			return FALSE;
		
		// Find nodes that right value is less than the left value
		$result = Database::instance()->query(Database::SELECT, 'SELECT count(*) as count FROM `'.$this->_table.'` 
			WHERE `'.$this->scope_column.'` = '.$root->scope.' 
			AND `'.$this->left_column.'` > `'.$this->right_column.'`', TRUE);
		if ($result[0]->count > 0)
			return FALSE;
		
		// Make sure no 2 nodes share a left/right value
		$i = 1;
		while ($i <= $end)
		{
			$result = Database::instance()->query(Database::SELECT, 'SELECT count(*) as count FROM `'.$this->_table.'` 
				WHERE `'.$this->scope_column.'` = '.$root->scope.' 
				AND (`'.$this->left_column.'` = '.$i.' OR `'.$this->right_column.'` = '.$i.')', TRUE);
			
			if ($result[0]->count > 1)
				return FALSE;
				
			$i++;
		}
		
		// Check to ensure that all nodes have a "correct" level
		
		return TRUE;
	}
	
	/**
	 * Force object to reload from database even if there are no changes
	 * 
	 * @return $this
	 */
	public function reload()
	{
		// Reset $_original for the primary key to force a reload
		$this->_original[$this->pk()] = NULL;
		
		return $this->load();
	}
		
	/**
	 * Generates the HTML for this node's descendants
	 *
	 * @param string $style pagination style.
	 * @param boolean $self include this node or not.
	 * @param string $direction direction to order the left column by.
	 * @return View
	 */
	public function render_descendants($style = NULL, $self = FALSE, $direction = 'ASC')
	{
		$nodes = $this->descendants($self, $direction);
		
		if ($style === NULL)
		{
			$style = $this->_style;
		}

		return View::factory($this->_directory.DIRECTORY_SEPARATOR.$style, array('nodes' => $nodes,'level_column' => $this->level_column));
	}
	
	/**
	 * Generates the HTML for this node's children
	 *
	 * @param string $style pagination style.
	 * @param boolean $self include this node or not.
	 * @param string $direction direction to order the left column by.
	 * @return View
	 */
	public function render_children($style = NULL, $self = FALSE, $direction = 'ASC')
	{
		$nodes = $this->children($self, $direction);
		
		if ($style === NULL)
		{
			$style = $this->_style;
		}

		return View::factory($this->_directory.DIRECTORY_SEPARATOR.$style, array('nodes' => $nodes,'level_column' => $this->level_column));
	}
}