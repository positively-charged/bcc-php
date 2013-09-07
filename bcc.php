#!/usr/bin/php
<?php

require_once 'src/common.php';
require_once 'src/f_token.php';
require_once 'src/f_stmt.php';
require_once 'src/f_dec.php';
require_once 'src/f_expr.php';
require_once 'src/b_chunk.php';
require_once 'src/b_opcode.php';
require_once 'src/b_code.php';
require_once 'src/b_tree.php';

function run( $args ) {
   array_shift( $args );
   $options = read_options( $args );
   if ( $options === false ) {
      return 1;
   }
   $tree = f_create_tree( $options );
   if ( $tree !== false ) {
      tp_show( $tree );
   //   b_publish( $tree, 'test/lib.o' );
   }
}

function read_options( $args ) {
   $options = array(
      'format' => k_format_little_e,
      'includes' => array(),
      'err_file' => false,
      'source_file' => '',
      'object_file' => '',
      'encrypt_str' => false,
   );
   $i = 0;
   $count = count( $args );
   while ( $i < $count ) {
      $name = '';
      $arg = $args[ $i ];
      if ( $arg[ 0 ] == '-' ) {
         if ( isset( $arg[ 1 ] ) ) {
            $name = $arg[ 1 ];
            $i += 1;
         }
         else {
            printf( "error: '-' without an option name\n" );
            return false;
         }
      }
      else {
         break;
      }
      switch ( $name ) {
      case 'i':
      case 'I':
         if ( $i < $count ) {
            array_push( $options[ 'includes' ], $args[ $i ] );
            $i += 1;
         }
         else {
            printf( "error: missing path for include-path option\n" );
            exit( 0 );
         }
         break;
      case 'e':
         $options[ 'err_file' ] = true;
         break;
      default:
         printf( "error: unknown option: %s\n", $name );
         return false;
      }
   }
   if ( $i < $count ) {
      $options[ 'source_file' ] = $args[ $i ];
      $i += 1;
   }
   else {
      printf( "error: missing source file to compile\n" );
      return false;
   }
   // Output file.
   if ( $i < $count ) {
      $options[ 'object_file' ] = $args[ $i ];
   }
   return $options;
}

run( $argv );

class tree_printer_t {
   public $depth;
}

function tp_show( $tree ) {
   $tp = new tree_printer_t();
   $tp->depth = 0;
   $module = $tree[ 'module' ];
   foreach ( $module->scripts as $script ) {
      tp_show_script( $tp, $script );
   }
}

function tp_inc_pad( $tp ) {
   $tp->depth += 1;
}

function tp_dec_pad( $tp ) {
   $tp->depth -= 1;
}

function tp_pad( $tp ) {
   for ( $i = 0; $i < $tp->depth; $i += 1 ) {
      echo '  ';
   }
}

function tp_show_text( $tp, $format ) {
   $args = func_get_args();
   array_shift( $args );
   array_shift( $args );
   vprintf( $format, $args );
}

function tp_show_line( $tp, $format ) {
   tp_pad( $tp );
   $args = func_get_args();
   array_shift( $args );
   array_shift( $args );
   vprintf( $format, $args );
   echo "\n";
}

function tp_show_script( $tp, $script ) {
   tp_show_line( $tp, 'script %d size(%d)', $script->number->value,
      $script->size );
   tp_show_block( $tp, $script->body );
}

function tp_show_block( $tp, $block ) {
   tp_inc_pad( $tp );
   foreach ( $block->stmts as $stmt ) {
      tp_show_node( $tp, $stmt );
   }
   tp_dec_pad( $tp );
}

function tp_show_node( $tp, $entity ) {
   switch ( $entity->node->type ) {
   case node_t::type_if:
      tp_show_if( $tp, $entity );
      break;
   case node_t::type_while:
      tp_show_while( $tp, $entity );
      break;
   case node_t::type_for:
      tp_show_for( $tp, $entity );
      break;
   case node_t::type_jump:
      tp_show_jump( $tp, $entity );
      break;
   default:
      tp_show_line( $tp, 'unknown-entity: %d', $entity->node->type );
      break;
   }
}

function tp_show_if( $tp, $stmt ) {
   tp_show_line( $tp, 'if' );
   tp_inc_pad( $tp );
   tp_dec_pad( $tp );
   tp_show_block( $tp, $stmt->body );
   if ( $stmt->else_body !== null ) {
      tp_print_ln( $tp, 'else' );
      tp_show_block( $tp, $stmt->else_body );
   }
}

function tp_show_while( $tp, $stmt ) {
   switch ( $stmt->type ) {
   case while_t::type_while:
      tp_show_line( $tp, 'while' );
      break;
   case while_t::type_until:
      tp_show_line( $tp, 'until' );
      break;
   case while_t::type_do_while:
      tp_show_line( $tp, 'do-while' );
      break;
   default:
      tp_show_line( $tp, 'do-until' );
      break;
   }
   tp_inc_pad( $tp );
   tp_dec_pad( $tp );
   tp_show_block( $tp, $stmt->body );
}

function tp_show_for( $tp, $stmt ) {
   tp_show_line( $tp, 'for' );
   tp_inc_pad( $tp );
   tp_show_line( $tp, 'init:' );
   tp_inc_pad( $tp );
   if ( $stmt->init ) {
      foreach ( $stmt->init as $node ) {
         tp_show_node( $tp, $node );
      }
   }
   else {
      tp_show_line( $tp, '(none)' );
   }
   tp_dec_pad( $tp );
   tp_show_line( $tp, 'condition:' );
   tp_inc_pad( $tp );
   if ( $stmt->cond ) {
      
   }
   else {
      tp_show_line( $tp, '(none)' );
   }
   tp_dec_pad( $tp );
   tp_show_line( $tp, 'post:' );
   tp_inc_pad( $tp );
   if ( $stmt->post ) {
      tp_show_expr( $tp, $stmt->post );
   }
   else {
      tp_show_line( $tp, '(none)' );
   }
   tp_dec_pad( $tp );
   tp_show_line( $tp, 'body:' );
   tp_show_block( $tp, $stmt->body );
   tp_dec_pad( $tp );
}

function tp_show_jump( $tp, $stmt ) {
   if ( $stmt->type == jump_t::type_break ) {
      tp_show_line( $tp, 'break' );
   }
   else {
      tp_show_line( $tp, 'continue' );
   }
}

function tp_show_expr( $tp, $expr ) {

}