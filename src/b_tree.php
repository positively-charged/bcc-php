<?php

function b_do_script( $back, $script ) {
   foreach ( $script->body->stmts as $node ) {
      switch ( $node->node->type ) {
      case node_t::script_jump:
         b_do_script_jump( $back, $node );
         break;
      case node_t::expr:
         b_do_expr( $back, $node );
         break;
      default:
         break;
      }
   }
   b_pcode( $back, pc_terminate );
}

function b_do_script_jump( $back, $jump ) {
   switch ( $jump->type ) {
   case script_jump_t::suspend;
      b_pcode( $back, pc_suspend );
      break;
   case script_jump_t::restart:
      b_pcode( $back, pc_restart );
      break;
   default:
      b_pcode( $back, pc_terminate );
      break;
   }
}

function b_do_expr( $back, $expr ) {
   $node = $expr->root->node;
   if ( $node->type == node_t::call ) {
      $call = $expr->root;
      b_do_call( $back, $call );
   }
   else if ( $node->type == node_t::literal ) {
      $literal = $expr->root;
      b_pcode( $back, pc_push_number, $literal->value );
   }
}

function b_do_call( $back, $call ) {
   $func = $call->func;
   if ( $func->type == func_t::type_ded ) {
      foreach ( $call->args as $arg ) {
         b_do_expr( $back, $arg );
      }
      b_pcode( $back, $func->detail[ 'opcode' ] );
   }
   else {

   }
}