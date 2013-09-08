<?php

function f_read_expr( $front ) {
   $pos = $front->tk_pos;
   $operand = f_read_op( $front );
   $expr = new expr_t();
   $expr->root = $operand[ 'node' ];
   $expr->value = $operand[ 'value' ];
   $expr->folded = $operand[ 'folded' ];
   $expr->pos = $pos;
   return $expr;
}

function f_read_expr_usable( $front ) {
   $pos = $front->tk_pos;
   $operand = f_read_op( $front );
   if ( $operand[ 'is_value' ] ) {
      $expr = new expr_t();
      $expr->root = $operand[ 'node' ];
      $expr->value = $operand[ 'value' ];
      $expr->folded = $operand[ 'folded' ];
      $expr->pos = $pos;
      return $expr;
   }
   else {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         k_diag_column, $pos, 'expression does not produce a usable value' );
      f_bail( $front );
   }
}

function f_read_op( $front ) {
   $operand = null;
   $add = null;
   $add_op = 0;
   $shift = null;
   $shift_op = 0;
   $lt = null;
   $lt_op = 0;
   $eq = null;
   $eq_op = 0;
   $bit_and = null;
   $bit_xor = null;
   $bit_or = null;
   $log_and = null;
   $log_or = null;

   top:
   $operand = f_read_operand( $front );

   mul:
   $op = 0;
   switch ( $front->tk ) {
   case tk_star:
      $op = binary_t::op_mul;
      break;
   case tk_slash:
      $op = binary_t::op_div;
      break;
   case tk_mod:
      $op = binary_t::op_mod;
   }
   if ( $op ) {
      f_read_tk( $front );
      $rside = f_read_operand( $front );
      f_add_binary( $operand, $op, $rside );
      goto mul;
   }

   if ( $add !== null ) {
      f_add_binary( $add, $add_op, $operand );
      $operand = $add;
      $add = null;
   }
   switch ( $front->tk ) {
   case tk_plus:
      $op = binary_t::op_add;
      break;
   case tk_minus:
      $op = binary_t::op_sub;
   }
   if ( $op ) {
      $add_op = $op;
      $add = $operand;
      f_read_tk( $front );
      goto top;
   }

   if ( $shift != null ) {
      f_add_binary( $shift, $shift_op, $operand );
      $operand = $shift;
      $shift = null;
   }
   switch ( $front->tk ) {
   case tk_shift_l:
      $op = binary_t::op_shift_l;
      break;
   case tk_shift_r:
      $op = binary_t::op_shift_r;
   }
   if ( $op ) {
      $shift_op = $op;
      $shift = $operand;
      f_read_tk( $front );
      goto top;
   }

   if ( $lt !== null ) {
      f_add_binary( $lt, $lt_op, $operand );
      $operand = $lt;
      $lt = null;
   }
   switch ( $front->tk ) {
   case tk_lt:
      $op = binary_t::op_less_than;
      break;
   case tk_lte:
      $op = binary_t::op_less_than_equal;
      break;
   case tk_gt:
      $op = binary_t::op_more_than;
      break;
   case tk_gte:
      $op = binary_t::op_more_than_equal;
   }
   if ( $op ) {
      $lt_op = $op;
      $lt = $operand;
      f_read_tk( $front );
      goto top;
   }

   if ( $eq !== null ) {
      f_add_binary( $eq, $eq_op, $operand );
      $operand = $eq;
      $eq = null;
   }
   switch ( $front->tk ) {
   case tk_eq:
      $op = binary_t::op_equal;
      break;
   case tk_neq:
      $op = binary_t::op_not_equal;
   }
   if ( $op ) {
      $eq_op = $op;
      $eq = $operand;
      f_read_tk( $front );
      goto top;
   }

   if ( $bit_and ) {
      f_add_binary( $bit_and, binary_t::op_bit_and, $operand );
      $operand = $bit_and;
      $bit_and = null;
   }
   if ( $front->tk == tk_bit_and ) {
      $bit_and = $operand;
      f_read_tk( $front );
      goto top;
   }

   if ( $bit_xor !== null ) {
      f_add_binary( $bit_xor, binary_t::op_bit_xor, $operand );
      $operand = $bit_xor;
      $bit_xor = null;
   }
   if ( $front->tk == tk_bit_xor ) {
      $bit_xor = $operand;
      f_read_tk( $front );
      goto top;
   }

   if ( $bit_or !== null ) {
      f_add_binary( $bit_or, binary_t::op_bit_or, $operand );
      $operand = $bit_or;
      $bit_or = null;
   }
   if ( $front->tk == tk_bit_or ) {
      $bit_or = $operand;
      f_read_tk( $front );
      goto top;
   }

   if ( $log_and !== null ) {
      f_add_binary( $log_and, binary_t::op_log_and, $operand );
      $operand = $log_and;
      $log_and = null;
   }
   if ( $front->tk == tk_log_and ) {
      $log_and = $operand;
      f_read_tk( $front );
      goto top;
   }

   if ( $log_or !== null ) {
      f_add_binary( $log_or, binary_t::op_log_or, $operand );
      $operand = $log_or;
      $log_or = null;
   }
   if ( $front->tk == tk_log_or ) {
      $log_or = $operand;
      f_read_tk( $front );
      goto top;
   }

   switch ( $front->tk ) { 
   case tk_assign:
      $op = binary_t::op_assign;
      break;
   case tk_assign_add:
      $op = binary_t::op_assign_add;
      break;
   case tk_assign_sub:
      $op = binary_t::op_assign_sub;
      break;
   case tk_assign_mul:
      $op = binary_t::op_assign_mul;
      break;
   case tk_assign_div:
      $op = binary_t::op_assign_div;
      break;
   case tk_assign_mod:
      $op = binary_t::op_assign_mod;
      break;
   case tk_assign_shift_l:
      $op = binary_t::op_assign_shift_l;
      break;
   case tk_assign_shift_r:
      $op = binary_t::op_assign_shift_r;
      break;
   case tk_assign_bit_and:
      $op = binary_t::op_assign_bit_and;
      break;
   case tk_assign_bit_xor:
      $op = binary_t::op_assign_bit_xor;
      break;
   case tk_assign_bit_or:
      $op = binary_t::op_assign_bit_or;
   }
   if ( $op ) {
      if ( ! $operand[ 'is_space' ] ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos,
            'left side cannot be used in assignment' );
         f_bail( $front );
      }
      f_read_tk( $front );
      $rside_pos = $front->tk_pos;
      $rside = f_read_op( $front );
      if ( ! $rside[ 'is_value' ] ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $rside_pos,
            'right side cannot be used in assignment' );
         f_bail( $front );
      }
      f_add_binary( $operand, $op, $rside );
      $operand[ 'is_value' ] = true;
      $operand[ 'is_space' ] = false;
   }

   return $operand;
}

function f_add_binary( &$operand, $op, $rside ) {
   $node = new binary_t();
   $node->lside = $operand[ 'node' ];
   $node->rside = $rside[ 'node' ];
   $node->op = $op;
   $operand[ 'node' ] = $node;
   if ( $operand[ 'folded' ] && $rside[ 'folded' ] ) {
      $lvalue = $operand[ 'value' ];
      $rvalue = $rside[ 'value' ];
      switch ( $op ) {
      case binary_t::op_add:
         $lvalue += $rvalue;
         break;
      case binary_t::op_sub:
         $lvalue -= $rvalue;
         break;
      case binary_t::op_mul:
         $lvalue *= $rvalue;
         break;
      default:
         break;
      }
      $operand[ 'value' ] = $lvalue;
   }
   else {
      $operand[ 'folded' ] = false;
   }
}

function f_read_operand( $front ) {
   // Prefix operations.
   $op = unary_t::op_none;
   switch ( $front->tk ) {
   case tk_inc:
      $op = unary_t::op_pre_inc;
      break;
   case tk_dec:
      $op = unary_t::op_pre_dec;
      break;
   case tk_minus:
      $op = unary_t::op_minus;
      break;
   case tk_log_not:
      $op = unary_t::op_log_not;
      break;
   case tk_bit_not:
      $op = unary_t::op_bit_not;
      break;
   default:
      break;
   }
   if ( $op ) {
      f_read_tk( $front );
      $pos = $front->tk_pos;
      $operand = f_read_operand( $front );
      if ( $op == unary_t::op_pre_inc || $op == unary_t::op_pre_dec ) {
         // Only an l-value can be incremented.
         if ( $operand[ 'is_space' ] ) {
            f_add_unary( $operand, $op );
            $operand[ 'is_space' ] = false;
         }
         else {
            $action = 'incremented';
            if ( $op == unary_t::op_pre_dec ) {
               $action = 'decremented';
            }
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $pos, 'operand cannot be %s', $action );
            f_bail( $front );
         }
      }
      // Remaining operations require a value to work on.
      else {
         if ( $operand[ 'is_value' ] ) {
            f_add_unary( $operand, $op );
            $operand[ 'is_space' ] = false;
         }
         else {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $pos,
               'operand cannot be used in unary operation' );
            f_bail( $front );
         }
      }
   }
   else {
      $operand = array(
         'node' => null,
         'folded' => false,
         'value' => 0,
         'is_value' => false,
         'is_space' => false,
         'array' => null,
         'dim' => -1,
      );
      f_read_primary( $front, $operand );
      if ( ! $front->reading_script_number ) {
         f_read_postfix( $front, $operand );
      }
   }
   return $operand;
}

function f_add_unary( &$operand, $op ) {
   $unary = new unary_t();
   $unary->op = $op;
   $unary->operand = $operand[ 'node' ];
   $operand[ 'node' ] = $unary;
}

function f_read_primary( $front, &$operand ) {
   if ( $front->tk == tk_id ) {
      $operand[ 'folded' ] = false;
      $entity = f_find_name( $front, $front->tk_text );
      if ( $entity ) {
         $operand[ 'node' ] = $entity;
         f_read_tk( $front );
         if ( $entity->node->type == node_t::type_constant ) {
            $operand[ 'value' ] = $entity->value;
            $operand[ 'folded' ] = true;
            $operand[ 'is_value' ] = true;
         }
         else if ( $entity->node->type == node_t::type_var ) {
            $operand[ 'is_value' ] = true;
            if ( $entity->dim ) {
               $operand[ 'array' ] = $entity;
               $operand[ 'dim' ] = 0;
            }
            else {
               $operand[ 'is_space' ] = true;
            }
         }
         else if ( $entity->node->type == node_t::type_func ) {
            $operand[ 'node' ] = $entity;
         }
      }
      // User functions can be used before declared.
      else if ( f_peek_tk( $front ) == tk_paren_l ) {
         $func = new func_t();
         $func->type = func_t::type_user;
         $func->return_type = null;
         $func->params = null;
         $func->min_params = 0;
         $func->opt_params = array();
         $func->detail = array( 'def' => false, 'def_params' => false );
         $front->scopes[ 0 ]->names[ $front->tk_text ] = $func;
         $operand[ 'node' ] = $func;
         $operand[ 'func' ] = $func;
         f_read_tk( $front ); 
      }
      else {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos, 'undefined object used: %s',
            $front->tk_text );
         f_bail( $front );
      }
   }
   else if ( $front->tk == tk_paren_l ) {
      f_read_tk( $front );
      $operand = f_read_op( $front );
      f_test_tk( $front, tk_paren_r );
      f_read_tk( $front );
   }
   else {
      $read = f_read_literal( $front );
      $literal = new literal_t();
      $literal->value = $read[ 'value' ];
      $operand[ 'node' ] = $literal;
      $operand[ 'value' ] = $literal->value;
      $operand[ 'folded' ] = true;
      $operand[ 'is_value' ] = true;
   }
}

function f_read_literal( $front ) {
   $pos = $front->tk_pos;
   $value = 0;
   if ( $front->tk == tk_lit_decimal ) {
      $value = ( int ) $front->tk_text;
      f_read_tk( $front );
   }
   else if ( $front->tk == tk_lit_octal ) {
      $value = ( int ) octdec( $front->tk_text );
      f_read_tk( $front );
   }
   else if ( $front->tk == tk_lit_hex ) {
      $value = ( int ) hexdec( $front->tk_text );
      f_read_tk( $front );
   }
   else if ( $front->tk == tk_lit_fixed ) {
      $num = ( float ) $front->tk_text;
      $whole = ( ( int ) $num ) << 16;
      $fraction = ( int ) ( ( 1 << 16 ) * ( $num - floor( $num ) ) );
      $value = $whole + $fraction;
      f_read_tk( $front );
   }
   else if ( $front->tk == tk_lit_char ) {
      $value = ord( $front->tk_text );
      f_read_tk( $front );
   }
   else if ( $front->tk == tk_lit_string ) {
      $value = array_search( $front->tk_text, $front->str_table );
      if ( $value === false ) {
         array_push( $front->str_table, $front->tk_text );
         $value = count( $front->str_table ) - 1;
      }
      f_read_tk( $front );
   }
   else {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $pos, 'missing literal value' );
      f_bail( $front );
   }
   return array(
      'pos' => $pos,
      'value' => $value,
   );
}

function f_read_postfix( $front, &$operand ) {
   while ( true ) {
      if ( $front->tk == tk_bracket_l ) {
         if ( ! $operand[ 'array' ] ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos,
               'accessing something not an array' );
            f_bail( $front );
         }
         // Don't go past available dimensions.
         else if ( $operand[ 'dim' ] == count( $operand[ 'array' ]->dim ) ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos,
               'array has no more dimensions to access' );
            f_bail( $front );
         }
         f_read_tk( $front );
         $expr = f_read_expr( $front );
         f_test_tk( $front, tk_bracket_r );
         f_read_tk( $front );
         $sub = new subscript_t();
         $sub->operand = $operand[ 'node' ];
         $sub->index = $expr;
         $operand[ 'node' ] = $sub;
         $operand[ 'dim' ] += 1;
         if ( $operand[ 'dim' ] == count( $operand[ 'array' ]->dim ) ) {
            $operand[ 'is_value' ] = true;
            $operand[ 'is_space' ] = true;
         }
         else {
            $operand[ 'is_value' ] = false;
            $operand[ 'is_space' ] = false;
         }
      }
      else if ( $front->tk == tk_inc || $front->tk == tk_dec ) {
         if ( $operand[ 'is_space' ] ) {
            $op = unary_t::op_post_inc;
            if ( $front->tk == tk_dec ) {
               $op = unary_t::op_post_dec;
            }
            f_read_tk( $front );
            f_add_unary( $operand, $op );
            $operand[ 'is_space' ] = false;
         }
         else {
            $action = 'incremented';
            if ( $front->tk == tk_dec ) {
               $action = 'decremented';
            }
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos,
               'operand to the left cannot be %s', $action );
            f_bail( $front );
         }
      }
      else if ( $front->tk == tk_paren_l ) {
         f_read_call( $front, $operand );
      }
      else {
         break;
      }
   }
}

function f_read_call( $front, &$operand ) {
   $pos = $front->tk_pos;
   f_test_tk( $front, tk_paren_l );
   f_read_tk( $front );
   $func = $operand[ 'node' ];
   if ( $func->node->type != node_t::type_func ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         k_diag_column, $pos,
         'calling something that is not a function' );
      f_bail( $front );
   }
   // Call to a latent function cannot appear in a function.
   else if ( $func->type == func_t::type_ded &&
      $func->detail[ 'latent' ] && $front->func ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         k_diag_column, $pos,
         'latent-function called inside function' );
      f_bail( $front );
   }
   $call = new call_t();
   $call->func = $func;
   $args_count = 0;
   if ( $front->tk == tk_id && f_peek_tk( $front ) == tk_colon ) {
      if ( $func->type != func_t::type_format ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos,
            'format-list argument given to non-format function' );
         f_bail( $front );
      }
      while ( true ) {
         f_test_tk( $front, tk_id );
         $cast = array();
         f_read_tk( $front );
         f_test_tk( $front, tk_colon );
         f_read_tk( $front );
         $expr = f_read_expr_usable( $front );
         // $item = new format_item_t();
         // $item->cast = $cast;
         // $item->expr = $expr;
         // array_push( $call->args, $item );
         if ( $front->tk == tk_comma ) {
            f_read_tk( $front );
         }
         else {
            break;
         }
      }
      // All format items count as a single argument.
      $args_count += 1;
      if ( $front->tk == tk_semicolon ) {
         f_read_tk( $front );
         while ( true ) {
            $arg = f_read_expr_usable( $front );
            array_push( $call->args, $arg );
            $args_count += 1;
            if ( $front->tk == tk_comma ) {
               f_read_tk( $front );
            }
            else {
               break;
            }
         }
      }
   }
   else {
      if ( $func->type == func_t::type_format ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos, 'missing format-list argument' );
         f_bail( $front );
      }
      // This relic is not necessary in new code. The compiler is smart enough
      // to figure out when to use the constant version of a pcode.
      if ( $front->tk == tk_const ) {
         f_read_tk( $front );
         f_test_tk( $front, tk_colon );
         f_read_tk( $front );
      }
      $update_params = false;
      if ( $func->type == func_t::type_user &&
         ! $func->detail[ 'def_params' ] ) {
         $func->detail[ 'def_params' ] = true;
         $update_params = true;
      }
      if ( $front->tk != tk_paren_r ) {
         while ( true ) {
            $arg = f_read_expr_usable( $front );
            array_push( $call->args, $arg );
            $args_count += 1;
            if ( $update_params ) {
               $func->min_params += 1;
               $func->max_params += 1;
            }
            if ( $front->tk == tk_comma ) {
               f_read_tk( $front );
            }
            else {
               break;
            }
         }
      }
   }
   if ( $args_count < $func->min_params || $args_count > $func->max_params ) {
      $count = $func->min_params;
      if ( $args_count > $func->max_params ) {
         $count = $func->max_params;
      }
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         k_diag_column, $pos, 'function expects %d argument%s but %d given',
         $count, ( $count == 1 ? '' : 's' ), $args_count );
      f_bail( $front );
   }
   f_test_tk( $front, tk_paren_r );
   f_read_tk( $front );
   $operand[ 'node' ] = $call;
   $operand[ 'func' ] = null;
   if ( $func->value ) {
      $operand[ 'is_value' ] = true;
   }
}