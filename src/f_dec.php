<?php

define( 'k_script_min_num', 0 );
define( 'k_script_max_num', 999 );
define( 'k_script_max_params', 3 );
define( 'k_max_map_locations', 128 );
define( 'k_max_world_locations', 256 );
define( 'k_max_global_locations', 64 );

function f_is_dec( $front ) {
   return in_array( $front->tk, array( tk_int, tk_str, tk_bool, tk_void,
      tk_function, tk_world, tk_global, tk_static ) );
}

function f_read_dec( $front, $area ) {
   $dec = array(
      'area' => $area,
      'pos' => $front->tk_pos,
      'storage' => k_storage_local,
      'storage_pos' => null,
      'storage_name' => '',
      'type' => null,
      'name' => '',
      'name_pos' => null,
      'dim' => array(),
      'dim_implicit' => null,
      'initials' => array(),
   );
   $is_func = false;
   if ( $front->tk == tk_function ) {
      $is_func = true;
      f_read_token( $front );
   }
   f_read_storage( $front, $dec );
   switch ( $front->tk ) {
   case tk_int:
   case tk_str:
   case tk_bool:
      $dec[ 'type' ] = $front->types[ $front->tk_text ];
      f_read_token( $front );
      break;
   case tk_void:
      f_read_token( $front );
      break;
   default:
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'expecting type in declaration' );
      f_bail( $front );
   }
   $is_var = false;
   while ( true ) {
      f_read_storage_index( $front, $dec );
      if ( $front->tk == tk_id ) {
         $pos = $front->tk_pos;
         $dec[ 'name' ] = f_read_unique_name( $front );
         $dec[ 'name_pos' ] = $pos;
      }
      else {
         // Parameters don't require a name.
         if ( $area != k_dec_param ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos,
               'missing name in declaration' );
            f_bail( $front );
         }
      }
      // Function.
      if ( ! $is_var ) {
         if ( $is_func || $front->tk == tk_paren_l ) {
            f_read_func( $front, $dec );
            break;
         }
         $is_var = true;
      }
      f_read_dim( $front, $dec );
      f_read_init( $front, $dec );
      if ( $area == k_dec_param ) {
         f_finish_param( $front, $dec );
         break;
      }
      else {
         f_finish_var( $front, $dec );
         if ( f_tk( $front ) == tk_comma ) {
            f_drop( $front );
         }
         else {
            f_skip( $front, tk_semicolon );
            break;
         }
      }
   }
}

function f_read_storage( $front, &$dec ) {
   if ( $front->tk == tk_global ) {
      $dec[ 'storage' ] = k_storage_global;
      $dec[ 'storage_pos' ] = $front->tk_pos;
      $dec[ 'storage_name' ] = $front->tk_text;
      f_read_token( $front );
   }
   else if ( $front->tk == tk_world ) {
      $dec[ 'storage' ] = k_storage_world;
      $dec[ 'storage_pos' ] = $front->tk_pos;
      $dec[ 'storage_name' ] = $front->tk_text;
      f_read_token( $front );
   }
   else if ( $front->tk == tk_static ) {
      $dec[ 'storage' ] = k_storage_map;
      if ( $dec[ 'area' ] == k_dec_for ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos,
            'static variable in for loop initialization' );
      }
      else if ( $dec[ 'area' ] == k_dec_param ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos,
            '\'static\' used in parameter' );
      }
      else if ( count( $front->scopes ) == 1 ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos,
            '\'static\' used in top scope' );
      }
      f_read_token( $front );
   }
   else if ( count( $front->scopes ) == 1 ) {
      $dec[ 'storage' ] = k_storage_map;
   }
}

function f_read_storage_index( $front, &$dec ) {
   if ( $front->tk == tk_lit_decimal ) {
      $pos = $front->tk_pos;
      $literal = f_read_literal( $front );
      $dec[ 'storage_index' ] = $literal[ 'value' ];
      f_skip( $front, tk_colon );
      $max_loc = k_max_world_locations;
      if ( $dec[ 'storage' ] == k_storage_global ) {
         $max_loc = k_max_global_locations;
      }
      else if ( $dec[ 'storage' ] != k_storage_world ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $pos,
            'index specified for %s storage', $dec[ 'storage_name' ] );
         f_bail( $front );
      }
      if ( $dec[ 'storage_index' ] >= $max_loc ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $pos,
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

function f_read_unique_name( $front ) {
   f_test( $front, tk_id );
   $name = $front->token->text;
   if ( isset( $front->scope->names[ $name ] ) ) {
      f_diag( $front, array( 'type' => 'err', 'msg' =>
         'name \'%s\' already used', 'args' => array( $name ) ) );
      f_bail( $front );
   }
   f_read_token( $front );
   return $name;
}

function f_read_dim( $front, &$dec ) {
   if ( $front->tk != tk_bracket_l ) {
      $dec[ 'dim' ] = array();
      $dec[ 'dim_implicit' ] = null;
      return;
   }
   // At this time, a local array is not allowed.
   if ( $dec[ 'storage' ] == k_storage_local ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         k_diag_column, $front->tk_pos, 'array in local scope' );
      f_bail( $front );
   }
   while ( true ) {
      if ( $front->tk != tk_bracket_l ) {
         break;
      }
      $pos = $front->tk_pos;
      f_read_token( $front );
      $expr = null;
      // Implicit size.
      if ( $front->tk == tk_bracket_r ) {
         // Only the first dimension can have an implicit size.
         if ( count( $dec[ 'dim' ] ) ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $pos, 'implicit size in subsequent dimension' );
            f_bail( $front );
         }
         f_read_token( $front );
      }
      else {
         $expr = f_read_expr( $front );
         f_skip( $front, tk_bracket_r );
         if ( ! $expr[ 'folded' ] ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $expr[ 'pos' ],
               'array size not constant expression' );
            f_bail( $front );
         }
         else if ( $expr[ 'value' ] <= 0 ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $expr[ 'pos' ], 'invalid array size' );
            f_bail( $front );
         }
      }
      $dim = new dim_t();
      array_push( $dec[ 'dim' ], $dim );
      if ( $expr === null ) {
         $dec[ 'dim_implicit' ] = $dim;
      }
      else {
         $dim->size = $expr[ 'value' ];
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
   f_read_token( $front );
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
         f_read_token( $front );
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
                  'initializer used to initialize a scalar variable' );
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
         f_read_token( $front );
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
         if ( ! $expr[ 'folded' ] ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $expr[ 'pos' ], 'initial value not constant' );
            f_bail( $front );
         }
         $initial = new initial_t();
         $initial->value = $expr[ 'node' ];
         array_push( $dec[ 'initials' ], $initial );
         $initz[ 'count' ] += 1;
         if ( $initz[ 'dim' ] == $dec[ 'dim_implicit' ] ) {
            $dec[ 'dim_implicit' ]->size += 1;
         }
         if ( $front->tk == tk_comma ) {
            f_read_token( $front );
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
   $var = f_make_var( $dec );
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

function f_make_var( $dec ) {
   $var = new var_t();
   $var->pos = $dec[ 'name_pos' ];
   $var->type = $dec[ 'type' ];
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
   return $var;
}

function f_read_func( $front, $dec ) {
   // No function allowed in parameter.
   if ( $dec[ 'area' ] == k_dec_param ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $dec[ 'pos' ], 'parameter is a function' );
      f_bail( $front );
   }
   // Cannot specify storage for a function.
   else if ( $dec[ 'storage' ] == k_storage_world ||
      $dec[ 'storage' ] == k_storage_global ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $dec[ 'storage_pos' ], 'storage specified for function' );
      f_bail( $front );
   }
   $params = new params_t();
   $params->pos = $front->tk_pos;
   $params->type = params_t::type_func;
   f_skip( $front, tk_paren_l );
   $scope = $front->scope;
   f_new_scope( $front );
   f_read_param_list( $front, $params );
   f_skip( $front, tk_paren_r );
   $func = new func_t();
   $func->return_type = $dec[ 'type' ];
   $scope->names[ $dec[ 'name' ] ] = $func;
   $detail = array();
   if ( $front->tk == tk_assign ) {
      f_read_token( $front );
      switch ( $front->bfunc_type ) {
      case func_t::type_aspec:
         $literal = f_read_literal( $front );
         $detail[ 'id' ] = $literal[ 'value' ];
         break;
      case func_t::type_ext:
         $literal = f_read_literal( $front );
         $detail[ 'id' ] = $literal[ 'value' ];
         break;
      case func_t::type_ded:
         $literal = f_read_literal( $front );
         $detail[ 'opcode' ] = $literal[ 'value' ];
         f_skip( $front, tk_comma );
         $literal = f_read_literal( $front );
         $detail[ 'opcode_c' ] = $literal[ 'value' ];
         f_skip( $front, tk_comma );
         $literal = f_read_literal( $front );
         $detail[ 'latent' ] = false;
         if ( $literal[ 'value' ] != 0 ) {
            $detail[ 'latent' ] = true;
         }
         break;
      default:
         $literal = f_read_literal( $front );
         $detail[ 'opcode' ] = $literal[ 'value' ];
         break;
      }
      f_skip( $front, tk_semicolon );
   }
   else {
      $block = new block_t();
      $block->in_func = true;
      f_read_block( $front, $block );
      $detail[ 'body' ] = $block;
      $detail[ 'index' ] = 0;
      $detail[ 'size' ] = 0;
      array_push( $front->module->funcs, $func );
   }
   f_pop_scope( $front );
   $func->detail = $detail;
}

function f_read_param_list( $front, $params ) {
   if ( $front->tk == tk_void ) {
      f_read_token( $front );
   }
   else {
      if ( $front->tk == tk_star ) {
         f_read_token( $front );
         if ( $front->tk == tk_comma ) {
            f_read_token( $front );
         }
      }
      $front->dec_params = $params;
      while ( true ) {
         if ( f_is_dec( $front ) ) {
            f_read_dec( $front, k_dec_param );
            if ( $front->tk == tk_comma ) {
               f_read_token( $front );
            }
            else {
               break;
            }
         }
         else {
            break;
         }
      }
   }
}

function f_finish_param( $front, $dec ) {
   if ( $front->dec_params->type == params_t::type_script ) {
      if ( $dec[ 'type' ]->name != 'int' ) {
         f_diag( $front, array( 'type' => 'err',
            'msg' => 'script parameter not of \'int\' type',
            'file' => $dec[ 'pos' ][ 'file' ]->path,
            'line' => $dec[ 'pos' ][ 'line' ],
            'column' => $dec[ 'pos' ][ 'column' ]
         ) );
      }
   }
   else {

   }
   $var = new var_t();
   $var->name = $dec[ 'name' ];
   $var->type = $dec[ 'type' ];
   array_push( $front->dec_params->vars, $var );
   if ( $var->name != '' ) {
      $front->scope->names[ $var->name ] = $var;
   }
}

function f_read_script( $front ) {
   f_skip( $front, tk_script );
   $script = new script_t();
   f_read_script_number( $front, $script );
   $params = new params_t();
   $params->pos = $front->tk_pos;
   $script->params = $params;
   if ( $front->tk == tk_paren_l ) {
      f_drop( $front );
      f_new_scope( $front );
      f_read_param_list( $front, $params );
      f_pop_scope( $front );
      f_skip( $front, tk_paren_r );
   }
   f_read_script_type( $front, $script );
   f_read_script_flag( $front, $script );
   f_read_block( $front, $script->body );
   array_push( $front->module->scripts, $script );
}

function f_read_script_number( $front, $script ) {
   $num = 0;
   if ( $front->tk == tk_shift_l ) {
      f_read_token( $front );
      if ( $front->tk == tk_lit_decimal ) {

      }
   }
   else {
      $front->reading_script_number = true;
      $expr = f_read_expr( $front );
print_r( $expr ); exit( 0 );
      $front->reading_script_number = false;
      if ( ! $expr[ 'folded' ] ) {
         f_diag( $front, array( 'msg' =>
            'script number not a constant expression', 'type' => 'err' ) );
         f_bail( $front );
      }
      $num = $expr[ 'value' ];
      if ( $num < k_script_min_num || $num > k_script_max_num ) {
         f_diag( $front, array( 'msg' =>
            'script number not between %d and %d', 'type' => 'err',
            'args' => array( k_script_min_num, k_script_max_num ) ) );
         f_bail( $front );
      }
      if ( $num == 0 ) {
         f_diag( $front, array( 'msg' =>
            'script number 0 not between << and >>', 'type' => 'err' ) );
      }
      $script->number = $num;
   }
   foreach ( $front->module->scripts as $script ) {
      if ( $script->number == $num ) {
         f_diag( $front, array( 'type' => 'err',
            'msg' => 'script number %d already used',
            'args' => array( $num )
         ) );
         //f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         //    k_diag_column, $pos,
         //   'script number %d already used', $num );
         break;
      }
   }
}

function f_read_script_type( $front, $script ) {
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
   $name = '';
   if ( isset( $types[ $front->tk ] ) ) {
      $script->type = $types[ $front->tk ];
      $name = $front->tk_text;
   }
   switch ( $script->type ) {
   case script_t::type_closed:
      if ( count( $script->params->vars ) > k_script_max_params ) {
         f_diag( $front, array( 'type' => 'err',
            'msg' => 'script has over maximum %d parameters',
            'args' => array( k_script_max_params ) ) );
      }
      break;
   case script_t::type_disconnect:
      f_read_token( $front );
      // A disconnect script must have a single parameter. It is the number of
      // the player who disconnected from the server.
      if( count( $script->params->vars ) != 1 ) {
         f_diag( $front, array( 'type' => 'err',
            'msg' => 'disconnect script missing player-number parameter',
            'file' => $script->params->pos[ 'file' ]->path,
            'line' => $script->params->pos[ 'line' ],
            'column' => $script->params->pos[ 'column' ]
         ) );
      }
      break;
   default:
      f_read_token( $front );
      if ( count( $script->params->vars ) != 0 ) {
         f_diag( $front, array( 'type' => 'err',
            'msg' => 'parameter list specified for %s script',
            'args' => array( $name )
         ) );
      }
      break;
   }
}

function f_read_script_flag( $front, $script ) {
   while ( true ) {
      $flag = script_t::flag_net;
      switch ( $front->tk ) {
      case tk_clientside:
         $flag = script_t::flag_clientside;
         break;
      case tk_net:
         break;
      default:
         return;
      }
      if ( ! ( $script->flags & $flag ) ) {
         $script->flags |= $flag;
         f_read_token( $front );
      }
      else {
         f_diag( $front, array( 'type' => 'err',
            'msg' => '%s flag already set',
            'args' => array( $front->tk_text ),
            'file' => $front->tk_pos[ 'file' ]->path,
            'line' => $front->tk_pos[ 'line' ],
            'column' => $front->tk_pos[ 'column' ] ) );
         f_bail( $front );
      }
   }
}