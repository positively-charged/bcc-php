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
      f_read_tk( $front );
      $rside = f_read_op( $front );
      f_add_binary( $operand, $op, $rside );
   }

   return $operand;
}

function f_add_binary( &$operand, $op, $rside ) {
   $node = new binary_t();
   $node->type = node_t::binary;
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
      $operand = f_read_operand( $front );
      f_add_unary( $operand, $op );
   }
   else {
      $operand = array(
         'node' => null,
         'folded' => false,
         'value' => 0
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
      $node = f_find_name( $front, $front->tk_text );
      if ( $node ) {
         $operand[ 'node' ] = $node;
         f_read_tk( $front );
      }
      // User functions can be used before declared.
      else if ( f_peek_tk( $front ) == tk_paren_l ) {
         $func = new func_t();
         $func->type = func_t::type_user;
         $func->return_type = null;
         $func->params = null;
         $func->min_params = 0;
         $func->opt_params = array();
         $func->detail = array( 'def' => false );
         $front->scopes[ 0 ]->names[ $front->tk_text ] = $func;
         $operand[ 'node' ] = $func;
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
      $sub = f_read_op( $front );
      $operand[ 'node' ] = $sub[ 'node' ];
      f_skip( $front, tk_paren_r );
   }
   else {
      $literal = f_read_literal( $front );
      $node = new literal_t();
      $node->type = node_t::literal;
      $node->value = $literal[ 'value' ];
      $operand[ 'node' ] = $node;
      $operand[ 'value' ] = $literal[ 'value' ];
      $operand[ 'folded' ] = true;
   }
}

function f_read_postfix( $front, &$operand ) {
   while ( true ) {
      switch ( $front->tk ) {
      case tk_bracket_l:
         f_read_tk( $front );
         $expr = f_read_expr( $front );
         f_skip( $front, tk_bracket_r );
         $sub = new subscript_t();
         $sub->operand = $operand[ 'node' ];
         $sub->index = $expr[ 'node' ];
         $operand[ 'node' ] = $sub;
         break;
      case tk_paren_l:
         f_read_call( $front, $operand );
         break;
      case tk_inc:
         f_add_unary( $operand, unary_t::op_post_inc );
         f_read_tk( $front );
         break;
      case tk_dec:
         f_add_unary( $operand, unary_t::op_post_dec );
         f_read_tk( $front );
         break;
      default:
         return;
      }
   }
}

function f_read_call( $front, &$operand ) {
   $pos = $front->tk_pos;
   f_test_tk( $front, tk_paren_l );
   f_read_tk( $front );
   $func = $operand[ 'node' ];
   if ( $func->node->type != node_t::func ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $pos, 'calling something that is not a function' );
      f_bail( $front );
   }
   // else if ( $func->type == func_t::type_ded &&
   $call = new call_t();
   $call->func = $func;
   $arg_count = 0;
   if ( $front->tk != tk_paren_r ) {
      f_read_call_args( $front, $operand, $call, $func );
   }
   f_test_tk( $front, tk_paren_r );
   f_read_tk( $front );
   $operand[ 'node' ] = $call;
//print_r( $operand ), "\n";
//exit( 0 );
}

function f_read_call_args( $front, &$operand, $call, $func ) {
   if ( $front->tk == tk_id && f_peek_tk( $front ) == tk_colon ) {
      if ( $func->type != func_t::type_format ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $pos, 'function not a format-function' );
         f_bail( $front );
      }
      while ( true ) {
         f_test_tk_tk( $front, tk_id );
         $cast = array();
         f_read_tk( $front );
         f_test_tk_tk( $front, tk_colon );
         f_read_tk( $front );
         $expr = f_read_expr( $front );
         $item = new format_item_t();
         $item->cast = $cast;
         $item->expr = $expr;
         array_push( $call->args, $item );
         if ( $front->tk == tk_comma ) {
            f_read_tk( $front );
         }
         else {
            break;
         }
      }
      $func->min_params += 1;
      $func->max_params += 1;
      if ( $front->tk == tk_semicolon ) {
         f_read_tk( $front );
      }
      else {
         return;
      }
   }
   else {
      if ( $func->type == func_t::type_format ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos, 'missing format-list argument' );
         f_bail( $front );
      }
   }
   while ( true ) {
      $arg = f_read_expr( $front );
      array_push( $call->args, $arg );
      if ( $func->type == func_t::type_user && ! $func->detail[ 'def' ] ) {
         $func->min_params += 1;
      }
      if ( $front->tk == tk_comma ) {
         f_read_tk( $front );
      }
      else {
         break;
      }
   }
}

function f_read_literal( $front ) {
   $value = 0;
   switch ( $front->tk ) {
   case tk_lit_decimal:
      $value = ( int ) $front->tk_text;
      f_read_tk( $front );
      break;
   case tk_lit_string:
      f_read_tk( $front );
      break;
   case tk_lit_hex:
   case tk_lit_octal:
   case tk_lit_fixed:
      f_read_tk( $front );
      break;
   default:
      break;
   }
   return array(
      'value' => $value,
   );
}

function f_expr_text_to_int( $text, $base ) {
   $length = strlen( $text );
   $result = 0;
   for ( $i = 0; $i < $length; $i += 1 ) {
      
   }
}