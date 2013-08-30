<?php

define( 'k_storage_local', 0 );
define( 'k_storage_map', 1 );
define( 'k_storage_world', 2 );
define( 'k_storage_global', 3 );

class file_t {
   public $path;
   public $load_path;
   public $text;
   public $length;
   public $pos;
   public $line;
   public $column;
   public $ch;
}

class type_t {
   public $name;
   public function __construct( $name ) {
      $this->name = $name;
   }
}

class dim_t {
   public $size;
   public $element_size;
   public function __construct() {
      $this->size = 0;
      $this->element_size = 0;
   }
}

class initial_t {
   const type_expr = 0;
   const type_jump = 1;
   public $type;
   public $value;
   public function __construct() {
      $this->type = self::type_expr;
      $this->value = 0;
   }
}

class params_t {
   const type_script = 0;
   const type_func = 1;

   public $pos;
   public $type;
   public $vars;
   public $has_default;

   public function __construct() {
      $this->pos = null;
      $this->type = self::type_script;
      $this->vars = array();
      $this->has_default = false;
   }
}

class scope_t {
   public $names;
   public $index;
   public $index_high;
   public function __construct() {
      $this->names = array();
      $this->index = 0;
      $this->index_high = 0;
   }
}

class node_t {
   const constant = 0;
   const literal = 1;
   const unary = 2;
   const binary = 3;
   const call = 4;
   const subscript = 5;
   const expr = 6;
   const variable = 7;
   const script = 8;
   const script_jump = 9;
   const func = 10;
   public $type;
}

class constant_t {
   public $node;
   public $value;
   public $pos;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::constant;
      $this->value = 0;
      $this->pos = null;
   }
}

class literal_t {
   public $node;
   public $value;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::literal;
   }
}

class unary_t {
   const op_none = 0;
   const op_minus = 1;
   const op_log_not = 2;
   const op_bit_not = 3;
   const op_pre_inc = 4;
   const op_pre_dec = 5;
   const op_post_inc = 6;
   const op_post_dec = 7;
   public $node;
   public $op;
   public $operand;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::unary;
   }
}

class binary_t {
   const op_assign = 1;
   const op_assign_add = 2;
   const op_assign_sub = 3;
   const op_assign_mul = 4;
   const op_assign_div = 5;
   const op_assign_mod = 6;
   const op_assign_shift_l = 7;
   const op_assign_shift_r = 8;
   const op_assign_bit_and = 9;
   const op_assign_bit_xor = 10;
   const op_assign_bit_or = 11;
   const op_log_or = 12;
   const op_log_and = 13;
   const op_bit_or = 14;
   const op_bit_xor = 15;
   const op_bit_and = 16;
   const op_equal = 17;
   const op_not_equal = 18;
   const op_less_than = 19;
   const op_less_than_equal = 20;
   const op_more_than = 21;
   const op_more_than_equal = 22;
   const op_shift_l = 23;
   const op_shift_r = 24;
   const op_add = 25;
   const op_sub = 26;
   const op_mul = 27;
   const op_div = 28;
   const op_mod = 29;
   public $node;
   public $op;
   public $lside;
   public $rside;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::binary;
   }
}

class call_t {
   public $node;
   public $func;
   public $args;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::call;
      $this->func = null;
      $this->args = array();
   }
}

class subscript_t {
   public $node;
   public $operand;
   public $index;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::subscript;
      $this->operand = null;
      $this->index = null;
   }
}

class expr_t {
   public $node;
   public $root;
   public $pos;
   public $folded;
   public $value;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::expr;
   }
}

class var_t {
   public $node;
   public $pos;
   public $type;
   public $name;
   public $dim;
   public $storage;
   public $index;
   public $size;
   public $initial;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::variable;
      $this->index = 0;
   }
}

class script_t {
   const type_closed = 0;
   const type_open = 1;
   const type_respawn = 2;
   const type_death = 3;
   const type_enter = 4;
   const type_pickup = 5;
   const type_blue_return = 6;
   const type_red_return = 7;
   const type_white_return = 8;
   const type_lightning = 9;
   const type_unloading = 10;
   const type_disconnect = 11;
   const type_return = 12;

   const flag_net = 1;
   const flag_clientside = 2;

   public $node;
   public $number;
   public $params;
   public $type;
   public $flags;
   public $offset;
   public $body;

   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::script;
      $this->number = 0;
      $this->params = array();
      $this->type = self::type_closed;
      $this->flags = 0;
      $this->offset = 0;
      $body = new block_t();
      $body->in_script = true;
      $this->body = $body;
   }
}

class block_t {
   const flow_going = 0;
   const flow_dead = 1;
   const flow_jump = 2;
   public $in_script;
   public $in_func;
   public $in_loop;
   public $is_break;
   public $is_continue;
   public $is_return;
   public $stmts;
   public $flow;
   public function __construct() {
      $this->in_script = false;
      $this->in_func = false;
      $this->in_loop = false;
      $this->stmts = array();
      $this->flow = self::flow_going;
   }
}

class script_jump_t {
   const terminate = 0;
   const suspend = 1;
   const restart = 2;
   public $node;
   public $type;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::script_jump;
      $this->type = self::terminate;
   }
}

class func_t {
   const type_aspec = 0;
   const type_ext = 1;
   const type_ded = 2;
   const type_format = 3;
   const type_user = 4;
   const type_internal = 5;
   public $node;
   public $type;
   public $return_type;
   public $params;
   public $min_params;
   public $max_params;
   public $opt_params;
   public $detail;
   public function __construct() {
      $this->node = new node_t();
      $this->node->type = node_t::func;
      $this->type = self::type_user;
   }
}

class module_t {
   public $vars;
   public $arrays;
   public $scripts;
   public $funcs;
   public $imports;
   public function __construct() {
      $this->vars = array();
      $this->arrays = array();
      $this->scripts = array();
      $this->funcs = array();
      $this->imports = array();
   }
}