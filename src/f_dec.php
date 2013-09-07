<?php

define( 'k_script_min_num', 0 );
define( 'k_script_max_num', 999 );
define( 'k_script_max_params', 3 );
define( 'k_max_map_locations', 128 );
define( 'k_max_world_locations', 256 );
define( 'k_max_global_locations', 64 );

function f_is_dec( $front ) {
   return in_array( $front->tk, array( tk_int, tk_str, tk_bool, tk_function,
      tk_world, tk_global, tk_static ) );
}

function f_read_dec( $front, $area ) {
   $dec = array(
      'area' => $area,
      'pos' => $front->tk_pos,
      'storage' => k_storage_local,
      'storage_pos' => null,
      'storage_name' => 'local',
      'value' => false,
      'name' => '',
      'name_pos' => null,
      'dim' => array(),
      'dim_implicit' => null,
      'initials' => array() );
   $func = false;
   if ( $front->tk == tk_function && $area == k_dec_top ) {
      f_read_tk( $front );
      $func = true;
   }
   // Storage.
   if ( ! $func ) {
      if ( $front->tk == tk_global ) {
         $dec[ 'storage' ] = k_storage_global;
         $dec[ 'storage_pos' ] = $front->tk_pos;
         $dec[ 'storage_name' ] = $front->tk_text;
         f_read_tk( $front );
      }
      else if ( $front->tk == tk_world ) {
         $dec[ 'storage' ] = k_storage_world;
         $dec[ 'storage_pos' ] = $front->tk_pos;
         $dec[ 'storage_name' ] = $front->tk_text;
         f_read_tk( $front );
      }
      else if ( $front->tk == tk_static ) {
         $dec[ 'storage' ] = k_storage_map;
         $dec[ 'storage_name' ] = 'map';
         if ( $area == k_dec_for ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos,
               'static variable in for loop initialization' );
         }
         else if ( $area == k_dec_param ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos,
               '\'static\' used in parameter' );
         }
         else if ( $area == k_dec_top ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos,
               '\'static\' used in top scope' );
         }
         f_read_tk( $front );
      }
      else if ( $area == k_dec_top ) {
         $dec[ 'storage' ] = k_storage_map;
         $dec[ 'storage_name' ] = 'map';
      }
   }
   // Type.
   switch ( $front->tk ) {
   case tk_int:
   case tk_str:
   case tk_bool:
      // Scripts can only have integer parameters.
      if ( $area == k_dec_param && $front->dec_params[ 'is_script' ] &&
         $front->tk != tk_int ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos,
            'script parameter not of \'int\' type' );
      }
      $dec[ 'value' ] = true;
      f_read_tk( $front );
      break;
   case tk_void:
      f_read_tk( $front );
      break;
   default:
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'expecting type in declaration' );
      f_bail( $front );
   }
   while ( true ) {
      // Storage index.
      if ( ! $func ) {
         if ( $front->tk == tk_lit_decimal ) {
            $literal = f_read_literal( $front );
            $dec[ 'storage_index' ] = $literal[ 'value' ];
            f_test_tk( $front, tk_colon );
            f_read_tk( $front );
            $max_loc = k_max_world_locations;
            if ( $dec[ 'storage' ] != k_storage_world ) {
               if ( $dec[ 'storage' ] == k_storage_global ) {
                  $max_loc = k_max_global_locations;
               }
               else  {
                  f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
                     k_diag_column, $literal[ 'pos' ],
                     'index specified for %s storage',
                     $dec[ 'storage_name' ] );
                  f_bail( $front );
               }
            }
            if ( $dec[ 'storage_index' ] >= $max_loc ) {
               f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
                  k_diag_column, $literal[ 'pos' ],
                  'index for %s storage not between 0 and %d',
                  $dec[ 'storage_name' ], $max_loc - 1 );
               f_bail( $front );
            }
         }
         else {
            // Index must be explicitly specified for these storages.
            if ( $dec[ 'storage' ] == k_storage_world ||
               $dec[ 'storage' ] == k_storage_global ) {
               f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
                  k_diag_column, $front->tk_pos,
                  'missing index for %s storage', $dec[ 'storage_name' ] );
               f_bail( $front );
            }
         }
      }
      // Name.
      if ( $front->tk == tk_id ) {
         $pos = $front->tk_pos;
         $dec[ 'name' ] = f_read_unique_name( $front );
         $dec[ 'name_pos' ] = $pos;
      }
      else {
         // Parameters don't require a name.
         if ( $area != k_dec_param ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos, 'missing name in declaration' );
            f_bail( $front );
         }
      }
      if ( $func ) {
         f_test_tk( $front, tk_paren_l );
         f_read_tk( $front );
         f_new_scope( $front );
         $num_params = f_read_param_list( $front, false );
         f_test_tk( $front, tk_paren_r );
         f_read_tk( $front );
         $func = new func_t();
         $func->pos = $dec[ 'name_pos' ];
         $func->value = $dec[ 'value' ];
         $func->min_params = $num_params;
         $func->max_params = $num_params;
         $front->scopes[ 0 ]->names[ $dec[ 'name' ] ] = $func;
         $block = new block_t();
         $front->block = $block;
         $front->func = $func;
         f_read_block( $front );
         f_pop_scope( $front );
         $front->block = null;
         $front->func = null;
         $func->detail = array(
            'body' => $block,
            'index' => 0,
            'size' => 0
         );
         array_push( $front->module->funcs, $func );
         break;
      }
      else {
         // Array dimensions.
         if ( $front->tk == tk_bracket_l ) {
            f_read_dim( $front, $dec );
         }
         else {
            $dec[ 'dim' ] = array();
            $dec[ 'dim_implicit' ] = null;
         }
         f_read_init( $front, $dec );
         if ( $area == k_dec_param ) {
            if ( $dec[ 'name' ] ) {
               $param = new param_t();
               $param->pos = $dec[ 'name_pos' ];
               $front->scope->names[ $dec[ 'name' ] ] = $param;
            }
            break;
         }
         else {
            f_finish_var( $front, $dec );
            if ( $front->tk == tk_comma ) {
               f_read_tk( $front );
            }
            else {
               f_test_tk( $front, tk_semicolon );
               f_read_tk( $front );
               break;
            }
         }
      }
   }
}

function f_read_unique_name( $front ) {
   f_test_tk( $front, tk_id );
   $name = $front->tk_text;
   if ( isset( $front->scope->names[ $name ] ) ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'name \'%s\' already used', $name );
      $entity = $front->scope->names[ $name ];
      switch ( $entity->node->type ) {
      case node_t::type_param:
      case node_t::type_var:
      case node_t::type_func:
         f_diag( $front, k_diag_file | k_diag_line | k_diag_column,
            $entity->pos, 'name previously used here' );
         f_bail( $front );
         break;
      default:
         f_bail( $front );
         break;
      }
   }
   else {
      f_read_tk( $front );
      return $name;
   }
}

function f_read_dim( $front, &$dec ) {
   // At this time, a local array is not allowed.
   if ( $dec[ 'storage' ] == k_storage_local ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         k_diag_column, $front->tk_pos, 'array in local scope' );
      f_bail( $front );
   }
   while ( $front->tk == tk_bracket_l ) {
      $pos = $front->tk_pos;
      f_read_tk( $front );
      $expr = null;
      // Implicit size.
      if ( $front->tk == tk_bracket_r ) {
         // Only the first dimension can have an implicit size.
         if ( count( $dec[ 'dim' ] ) ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $pos, 'implicit size in subsequent dimension' );
            f_bail( $front );
         }
         f_read_tk( $front );
      }
      else {
         $expr = f_read_expr( $front );
         f_test_tk( $front, tk_bracket_r );
         f_read_tk( $front );
         if ( ! $expr->folded ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $expr->pos,
               'array size not a constant expression' );
            f_bail( $front );
         }
         else if ( $expr->value <= 0 ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $expr->pos, 'invalid array size' );
            f_bail( $front );
         }
      }
      $dim = new dim_t();
      array_push( $dec[ 'dim' ], $dim );
      if ( ! $expr ) {
         $dec[ 'dim_implicit' ] = $dim;
      }
      else {
         $dim->size = $expr->value;
      }
   }
   $i = count( $dec[ 'dim' ] ) - 1;
   // For now, each element of the last dimension is 1 integer in size. 
   $dec[ 'dim' ][ $i ]->element_size = 1;
   while ( $i > 0 ) {
      $dec[ 'dim' ][ $i - 1 ]->element_size =
         $dec[ 'dim' ][ $i ]->element_size *
         $dec[ 'dim' ][ $i ]->size;
      $i -= 1;
   }
}

function f_read_init( $front, &$dec ) {
   if ( $front->tk != tk_assign ) {
      if ( $dec[ 'dim_implicit' ] && ( (
         $dec[ 'storage' ] != k_storage_world &&
         $dec[ 'storage' ] != k_storage_global ) ||
         count( $dec[ 'dim' ] ) > 1 ) ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos, 'missing initialization' );
         f_bail( $front );
      }
      return;
   }
   // At this time, there is no way to initialize an array at top scope with
   // world or global storage.
   if ( ( $dec[ 'storage' ] == k_storage_world ||
      $dec[ 'storage' ] == k_storage_global ) &&
      count( $front->scopes ) == 1 ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'initialization of variable with %s storage ' .
         'at top scope', $dec[ 'storage_name' ] );
      f_bail( $front );
   }
   f_read_tk( $front );
   if ( $front->tk == tk_brace_l ) {
      f_read_initz( $front, $dec );
   }
   else {
      if ( count( $dec[ 'dim' ] ) ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos,
            'array initialization missing initializer' );
         f_bail( $front );
      }
      $expr = f_read_expr( $front );
      if ( $dec[ 'storage' ] == k_storage_map && ! $expr[ 'folded' ] ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $expr[ 'pos' ], 'initial value not constant' );
         f_bail( $front );
      }
      $initial = new initial_t();
      $initial->value = $expr[ 'node' ];
      array_push( $dec[ 'initials' ], $initial );
   }
}

function f_read_initz( $front, &$dec ) {
   $initz = null;
   $stack = array();
   $dim_next = 0;
   $index = 0;
   while ( true ) {
      // NOTE: This block must run first.
      if ( $front->tk == tk_brace_l ) {
         $initz = array(
            'pos' => $front->tk_pos,
            'count' => 0,
            'dim' => null
         );
         f_read_tk( $front );
         if ( $dim_next == count( $dec[ 'dim' ] ) ) {
            if ( $dim_next ) {
               f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
                  k_diag_column, $initz[ 'pos' ],
                  'array does not have another dimension to initialize' );
               f_bail( $front );
            }
            else {
               f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
                  k_diag_column, $initz[ 'pos' ],
                  'initializer used on a scalar variable' );
               f_bail( $front );
            }
         }
         else {
            $initz[ 'dim' ] = $dec[ 'dim' ][ $dim_next ];
            array_push( $stack, $initz );
            $dim_next += 1;
         }
      }
      else if ( $front->tk == tk_brace_r ) {
         f_read_tk( $front );
         if ( $initz[ 'count' ] ) {
            $index += $initz[ 'count' ];
            array_pop( $stack );
            if ( count( $stack ) ) {
               $initz = end( $stack );
               $initz[ 'count' ] += 1;
               if ( $initz[ 'dim' ] == $dec[ 'dim_implicit' ] ) {
                  $dec[ 'dim_implicit' ]->size += 1;
               }
               $left = ( $initz[ 'dim' ]->size - $initz[ 'count' ] ) *
                  $initz[ 'dim' ]->element_size;
               if ( $left ) {
                  $index += $left;
                  $initial = new initial_t();
                  $initial->type = initial_t::type_jump;
                  $initial->value = $index;
                  array_push( $dec[ 'initials' ], $initial );
               }
            }
            else {
               break;
            }
         }
         else {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $initz[ 'pos' ], 'initializer is empty' );
            f_bail( $front );
         }
      }
      else {
         if ( $dim_next != count( $dec[ 'dim' ] ) ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos, 'missing another initializer' );
            f_bail( $front );
         }
         $expr = f_read_expr( $front );
         if ( ! $expr->folded ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $expr->pos, 'initial value not constant' );
            f_bail( $front );
         }
         $initial = new initial_t();
         $initial->value = $expr;
         array_push( $dec[ 'initials' ], $initial );
         $initz[ 'count' ] += 1;
         if ( $initz[ 'dim' ] == $dec[ 'dim_implicit' ] ) {
            $dec[ 'dim_implicit' ]->size += 1;
         }
         if ( $front->tk == tk_comma ) {
            f_read_tk( $front );
         }
      }
      // Don't go over the dimension size. This does not apply to an implicit
      // dimension.
      if ( $initz[ 'count' ] > $initz[ 'dim' ]->size ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $initz[ 'pos' ],
            'too many elements in initializer for dimension of size %d',
            $initz[ 'dim' ]->size );
         f_bail( $front );
      }
   }
}

function f_finish_var( $front, $dec ) {
   $var = new var_t();
   $var->pos = $dec[ 'name_pos' ];
   $var->name = $dec[ 'name' ];
   $var->storage = $dec[ 'storage' ];
   $var->dim = $dec[ 'dim' ];
   $var->size = 1;
   if ( $var->dim ) {
      $var->size = $var->dim[ 0 ]->size * $var->dim[ 0 ]->element_size;
      $var->initial = $dec[ 'initials' ];
   }
   else {
      $var->initial = array_pop( $dec[ 'initials' ] );
   }
   $front->scope->names[ $var->name ] = $var;
   if ( $dec[ 'area' ] == k_dec_top ) {
      if ( $var->dim ) {
         array_push( $front->module->arrays, $var );
      }
      else {
         array_push( $front->module->vars, $var );
      }
   }
   else if ( $dec[ 'area' ] == k_dec_local ) {
      $var->index = f_alloc_index( $front );
      array_push( $front->block->stmts, $var );
   }
   else {
      $var->index = f_alloc_index( $front );
      array_push( $front->dec_for_init, $var );
   }
}

function f_read_param_list( $front, $is_script ) {
   if ( $front->tk == tk_void ) {
      f_read_tk( $front );
      return 0;
   }
   else {
      $front->dec_params = array(
         'is_script' => $is_script );
      $count = 0;
      if ( f_is_dec( $front ) ) {
         f_read_dec( $front, k_dec_param );
         $count += 1;
         while ( $front->tk == tk_comma ) {
            f_read_tk( $front );
            f_read_dec( $front, k_dec_param );
            $count += 1;
         }
      }
      return $count;
   }
}

function f_read_script( $front ) {
   f_test_tk( $front, tk_script );
   $script = new script_t();
   $script->pos = $front->tk_pos;
   f_read_tk( $front );
   // Script number.
   $number_pos = null;
   if ( $front->tk == tk_shift_l ) {
      f_read_tk( $front );
      // The token between the << and >> tokens must be the digit zero.
      if ( $front->tk == tk_lit_decimal && $front->tk_text == '0' ) {
         $number_pos = $front->tk_pos;
         f_read_tk( $front );
         f_test_tk( $front, tk_shift_r );
         f_read_tk( $front );
      }
      else {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos, 'missing the digit 0' );
         f_bail( $front );
      }
   }
   else {
      $front->reading_script_number = true;
      $expr = f_read_expr( $front );
      $number_pos = $expr->pos;
      $front->reading_script_number = false;
      if ( ! $expr->folded ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $expr->pos,
            'script number not a constant expression' );
         f_bail( $front );
      }
      else if ( $expr->value < k_script_min_num ||
         $expr->value > k_script_max_num ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $expr->pos, 'script number not between %d and %d',
            k_script_min_num, k_script_max_num );
         f_bail( $front );
      }
      else if ( $expr->value == 0 ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $expr->pos,
            'script number 0 not between << and >>' );
      }
      else {
         $script->number = $expr->value;
      }
   }
   // There should be no duplicate scripts in the same module.
   foreach ( $front->module->scripts as $older_script ) {
      if ( $script->number == $older_script->number ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $number_pos, 'script number %d already used',
            $script->number );
         f_diag( $front, k_diag_file | k_diag_line | k_diag_column,
            $older_script->pos, 'first script to use number found here' );
         break;
      }
   }
   f_new_scope( $front );
   // Parameter list.
   $params_pos = null;
   if ( $front->tk == tk_paren_l ) {
      $params_pos = $front->tk_pos;
      f_read_tk( $front );
      $script->num_params = f_read_param_list( $front, true );
      f_test_tk( $front, tk_paren_r );
      f_read_tk( $front );
   }
   // Script type.
   $types = array(
      tk_open => script_t::type_open,
      tk_respawn => script_t::type_respawn,
      tk_death => script_t::type_death,
      tk_enter => script_t::type_enter,
      tk_pickup => script_t::type_pickup,
      tk_blue_return => script_t::type_blue_return,
      tk_red_return => script_t::type_red_return,
      tk_white_return => script_t::type_white_return,
      tk_lightning => script_t::type_lightning,
      tk_disconnect => script_t::type_disconnect,
      tk_unloading => script_t::type_unloading,
      tk_return => script_t::type_return
   );
   if ( isset( $types[ $front->tk ] ) ) {
      $script->type = $types[ $front->tk ];
   }
   switch ( $script->type ) {
   case script_t::type_closed:
      if ( $script->num_params > k_script_max_params ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $params_pos,
            'script has over maximum %d parameters', k_script_max_params );
      }
      break;
   case script_t::type_disconnect:
      // A disconnect script must have a single parameter. It is the number of
      // the player who disconnected from the server.
      if( $script->num_params != 1 ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $params_pos,
            'disconnect script missing one player-number parameter' );
      }
      f_read_tk( $front );
      break;
   default:
      if ( $script->num_params != 0 ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $params_pos,
            'parameter list specified for %s script', $front->tk_text );
      }
      f_read_tk( $front );
      break;
   }
   // Script flags.
   while ( true ) {
      $flag = script_t::flag_net;
      if ( $front->tk != tk_net ) {
         if ( $front->tk == tk_clientside ) {
            $flag = script_t::flag_clientside;
         }
         else {
            break;
         }
      }
      if ( ! ( $script->flags & $flag ) ) {
         $script->flags |= $flag;
         f_read_tk( $front );
      }
      else {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos, '%s flag already set',
            $front->tk_text );
         f_read_tk( $front );
      }
   }
   // Body.
   $block = new block_t();
   $block->in_script = true;
   $front->block = $block;
   f_read_block( $front );
   $front->block = null;
   $script->body = $block;
   f_pop_scope( $front );
   array_push( $front->module->scripts, $script );
}

function f_read_bfunc_list( $front ) {
   f_test_tk( $front, tk_special );
   f_read_tk( $front );
   while ( true ) {
      $ext = false;
      if ( $front->tk == tk_minus ) {
         f_read_tk( $front );
         $ext = true;
      }
      $code = f_read_literal( $front );
      f_test_tk( $front, tk_colon );
      f_read_tk( $front );
      $name = f_read_unique_name( $front );
      f_test_tk( $front, tk_paren_l );
      f_read_tk( $front );
      $min_param = 0;
      $max_param = f_read_literal( $front );
      if ( $front->tk == tk_comma ) {
         $min_param = $max_param;
         f_read_tk( $front );
         $max_param = f_read_literal( $front );
      }
      f_test_tk( $front, tk_paren_r );
      f_read_tk( $front );
      $func = new func_t();
      $func->min_params = $min_param;
      $func->max_params = $max_param;
      $func->value = true;
      $front->scopes[ 0 ]->names[ $name ] = $func;
      if ( $ext ) {
         $func->type = func_t::type_ext;
         $func->detail[ 'id' ] = $code;
      }
      else {
         $func->type = func_t::type_aspec;
         $func->detail[ 'id' ] = $code;
      }
      if ( $front->tk == tk_semicolon ) {
         f_read_tk( $front );
         break;
      }
      else {
         f_test_tk( $front, tk_comma );
         f_read_tk( $front );
      }
   }
}

function f_load_ded_format_funcs( $front ) {
   $ded = array(
      // Format: name / returns-value / parameter-count / parameter-minimum /
      // opcode / is-latent.
      array( 'delay', false, 1, 1, pc_delay, true ),
      array( 'random', true, 2, 2, pc_random, false ),
      array( 'thingcount', true, 2, 2, pc_thing_count, false ),
      array( 'tagwait', false, 1, 1, pc_tag_wait, true ),
      array( 'polywait', false, 1, 1, pc_poly_wait, true ),
      array( 'changefloor', false, 2, 2, pc_change_floor, false ),
      array( 'changeceiling', false, 2, 2, pc_change_ceiling, false ),
      array( 'lineside', true, 0, 0, pc_line_side, false ),
      array( 'scriptwait', false, 1, 1, pc_script_wait, true ),
      array( 'clearlinespecial', false, 0, 0, pc_clear_line_special, false ),
      array( 'playercount', true, 0, 0, pc_player_count, false ),
      array( 'gametype', true, 0, 0, pc_game_type, false ),
      array( 'gameskill', true, 0, 0, pc_game_skill, false ),
      array( 'timer', true, 0, 0, pc_timer, false ),
      array( 'sectorsound', false, 2, 2, pc_sector_sound, false ),
      array( 'ambientsound', false, 2, 2, pc_ambient_sound, false ),
      array( 'soundsequence', false, 1, 1, pc_sound_sequence, false ),
      array( 'setlinetexture', false, 4, 4, pc_set_line_texture, false ),
      array( 'setlineblocking', false, 2, 2, pc_set_line_blocking, false ),
      array( 'setlinespecial', false, 7, 2, pc_set_line_special, false ),
      array( 'thingsound', false, 3, 3, pc_thing_sound, false ),
      array( 'activatorsound', false, 2, 2, pc_activator_sound, false ),
      array( 'localambientsound', false, 2, 2, pc_local_ambient_sound,
         false ),
      array( 'setlinemonsterblocking', false, 2, 2,
         pc_set_line_monster_blocking, false ),
      array( 'ismultiplayer', true, 0, 0, pc_is_multiplayer, false ),
      array( 'playerteam', true, 0, 0, pc_player_team, false ),
      array( 'playerhealth', true, 0, 0, pc_player_health, false ),
      array( 'playerarmorpoints', true, 0, 0, pc_player_armor_points, false ),
      array( 'playerfrags', true, 0, 0, pc_player_frags, false ),
      array( 'bluecount', true, 0, 0, pc_blue_team_count, false ),
      array( 'blueteamcount', true, 0, 0, pc_blue_team_count, false ),
      array( 'redcount', true, 0, 0, pc_red_team_count, false ),
      array( 'redteamcount', true, 0, 0, pc_red_team_count, false ),
      array( 'bluescore', true, 0, 0, pc_blue_team_score, false ),
      array( 'blueteamscore', true, 0, 0, pc_blue_team_score, false ),
      array( 'redscore', true, 0, 0, pc_red_team_score, false ),
      array( 'redteamscore', true, 0, 0, pc_red_team_score, false ),
      array( 'isoneflagctf', true, 0, 0, pc_is_one_flag_ctf, false ),
      array( 'getinvasionwave', true, 0, 0, pc_get_invasion_wave, false ),
      array( 'getinvasionstate', true, 0, 0, pc_get_invasion_state, false ),
      array( 'music_change', false, 2, 2, pc_music_change, false ),
      array( 'consolecommand', false, 3, 1, pc_console_command, false ),
      array( 'singleplayer', true, 0, 0, pc_single_player, false ),
      array( 'fixedmul', true, 2, 2, pc_fixed_mul, false ),
      array( 'fixeddiv', true, 2, 2, pc_fixed_div, false ),
      array( 'setgravity', false, 1, 1, pc_set_gravity, false ),
      array( 'setaircontrol', false, 1, 1, pc_set_air_control, false ),
      array( 'clearinventory', false, 0, 0, pc_clear_inventory, false ),
      array( 'giveinventory', false, 2, 2, pc_give_inventory, false ),
      array( 'takeinventory', false, 2, 2, pc_take_inventory, false ),
      array( 'checkinventory', true, 1, 1, pc_check_inventory, false ),
      array( 'spawn', true, 6, 4, pc_spawn, false ),
      array( 'spawnspot', true, 4, 2, pc_spawn_spot, false ),
      array( 'setmusic', false, 3, 1, pc_set_music, false ),
      array( 'localsetmusic', false, 3, 1, pc_local_set_music, false ),
      array( 'setfont', false, 1, 1, pc_set_font, false ),
      array( 'setthingspecial', false, 7, 2, pc_set_thing_special, false ),
      array( 'fadeto', false, 5, 5, pc_fade_to, false ),
      array( 'faderange', false, 9, 9, pc_fade_range, false ),
      array( 'cancelfade', false, 0, 0, pc_cancel_fade, false ),
      array( 'playmovie', true, 1, 1, pc_play_movie, false ),
      array( 'setfloortrigger', false, 8, 3, pc_set_floor_trigger, false ),
      array( 'setceilingtrigger', false, 8, 3, pc_set_ceiling_trigger, false ),
      array( 'getactorx', true, 1, 1, pc_get_actor_x, false ),
      array( 'getactory', true, 1, 1, pc_get_actor_y, false ),
      array( 'getactorz', true, 1, 1, pc_get_actor_z, false ),
      array( 'sin', true, 1, 1, pc_sin, false ),
      array( 'cos', true, 1, 1, pc_cos, false ),
      array( 'vectorangle', true, 2, 2, pc_vector_angle, false ),
      array( 'checkweapon', true, 1, 1, pc_check_weapon, false ),
      array( 'setweapon', true, 1, 1, pc_set_weapon, false ),
      array( 'setmarineweapon', false, 2, 2, pc_set_marine_weapon, false ),
      array( 'setactorproperty', false, 3, 3, pc_set_actor_property, false ),
      array( 'getactorproperty', true, 2, 2, pc_get_actor_property, false ),
      array( 'playernumber', true, 0, 0, pc_player_number, false ),
      array( 'activatortid', true, 0, 0, pc_activator_tid, false ),
      array( 'setmarinesprite', false, 2, 2, pc_set_marine_sprite, false ),
      array( 'getscreenwidth', true, 0, 0, pc_get_screen_width, false ),
      array( 'getscreenheight', true, 0, 0, pc_get_screen_height, false ),
      array( 'thing_projectile2', false, 7, 7, pc_thing_projectile2, false ),
      array( 'strlen', true, 1, 1, pc_strlen, false ),
      array( 'sethudsize', false, 3, 3, pc_set_hud_size, false ),
      array( 'getcvar', true, 1, 1, pc_get_cvar, false ),
      array( 'setresultvalue', false, 1, 1, pc_set_result_value, false ),
      array( 'getlinerowoffset', true, 0, 0, pc_get_line_row_offset, false ),
      array( 'getactorfloorz', true, 1, 1, pc_get_actor_floor_z, false ),
      array( 'getactorangle', true, 1, 1, pc_get_actor_angle, false ),
      array( 'getsectorfloorz', true, 3, 3, pc_get_sector_floor_z, false ),
      array( 'getsectorceilingz', true, 3, 3, pc_get_sector_ceiling_z, false ),
      array( 'getsigilpieces', true, 0, 0, pc_get_sigil_pieces, false ),
      array( 'getlevelinfo', true, 1, 1, pc_get_level_info, false ),
      array( 'changesky', false, 2, 2, pc_change_sky, false ),
      array( 'playeringame', true, 1, 1, pc_player_in_game, false ),
      array( 'playerisbot', true, 1, 1, pc_player_is_bot, false ),
      array( 'setcameratotexture', false, 3, 3, pc_set_camera_to_texture,
         false ),
      array( 'getammocapacity', true, 1, 1, pc_get_ammo_capacity, false ),
      array( 'setammocapacity', false, 2, 2, pc_set_ammo_capacity, false ),
      array( 'setactorangle', false, 2, 2, pc_set_actor_angle, false ),
      array( 'spawnprojectile', false, 7, 7, pc_spawn_projectile, false ),
      array( 'getsectorlightlevel', true, 1, 1, pc_get_sector_light_level,
         false ),
      array( 'getactorceilingz', true, 1, 1, pc_get_actor_ceiling_z, false ),
      array( 'clearactorinventory', false, 1, 1, pc_clear_actor_inventory,
         false ),
      array( 'giveactorinventory', false, 3, 3, pc_give_actor_inventory,
         false ),
      array( 'takeactorinventory', false, 3, 3, pc_take_actor_inventory,
         false ),
      array( 'checkactorinventory', true, 2, 2, pc_check_actor_inventory,
         false ),
      array( 'thingcountname', true, 2, 2, pc_thing_count_name, false ),
      array( 'spawnspotfacing', true, 3, 2, pc_spawn_spot_facing, false ),
      array( 'playerclass', true, 1, 1, pc_player_class, false ),
      array( 'getplayerinfo', true, 2, 2, pc_get_player_info, false ),
      array( 'changelevel', false, 4, 3, pc_change_level, false ),
      array( 'sectordamage', false, 5, 5, pc_sector_damage, false ),
      array( 'replacetextures', false, 3, 2, pc_replace_textures, false ),
      array( 'getactorpitch', true, 1, 1, pc_get_actor_pitch, false ),
      array( 'setactorpitch', false, 2, 2, pc_set_actor_pitch, false ),
      array( 'setactorstate', true, 3, 2, pc_set_actor_state, false ),
      array( 'thing_damage2', true, 3, 3, pc_thing_damage2, false ),
      array( 'useinventory', true, 1, 1, pc_use_inventory, false ),
      array( 'useactorinventory', true, 2, 2, pc_use_actor_inventory, false ),
      array( 'checkactorceilingtexture', true, 2, 2,
         pc_check_actor_ceiling_texture, false ),
      array( 'checkactorfloortexture', true, 2, 2,
         pc_check_actor_floor_texture, false ),
      array( 'getactorlightlevel', true, 1, 1, pc_get_actor_light_level,
         false ),
      array( 'setmugshotstate', false, 1, 1, pc_set_mugshot_state, false ),
      array( 'thingcountsector', true, 3, 3, pc_thing_count_sector, false ),
      array( 'thingcountnamesector', true, 3, 3, pc_thing_count_name_sector,
         false ),
      array( 'checkplayercamera', true, 1, 1, pc_check_player_camera, false ),
      array( 'morphactor', true, 7, 7, pc_morph_actor, false ),
      array( 'unmorphactor', true, 2, 1, pc_unmorph_actor, false ),
      array( 'getplayerinput', true, 2, 2, pc_get_player_input, false ),
      array( 'classifyactor', true, 1, 1, pc_classify_actor, false )
   );
   foreach ( $ded as $template ) {
      list( $name, $value, $max_params, $min_params, $opcode, $latent ) =
         $template;
      $func = new func_t();
      $func->type = func_t::type_ded;
      $func->value = $value;
      $func->min_params = $min_params;
      $func->max_params = $max_params;
      $func->detail = array(
         'opcode' => $opcode,
         'latent' => $latent );
      $front->scope->names[ $name ] = $func;
   }
   $format = array(
      // Format: name / returns-value / parameter-count / parameter-minimum /
      // terminating-opcode.
      // Note: The format items together count as a single parameter.
      array( 'print', false, 1, 1, pc_end_print ),
      array( 'printbold', false, 1, 1, pc_end_print_bold ),
      array( 'hudmessage', false, 9, 7, pc_end_hud_message ),
      array( 'hudmessagebold', false, 9, 7, pc_end_hud_message_bold ),
      array( 'log', false, 1, 1, pc_end_log ),
      array( 'strparam', true, 1, 1, pc_save_string )
   );
   foreach ( $format as $template ) {
      list( $name, $value, $max_params, $min_params, $opcode ) = $template;
      $func = new func_t();
      $func->type = func_t::type_format;
      $func->value = $value;
      $func->min_params = $min_params;
      $func->max_params = $max_params;
      $func->detail = array(
         'opcode' => $opcode );
      $front->scope->names[ $name ] = $func;
   }
}