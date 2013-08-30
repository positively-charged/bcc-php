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
   print_r( $tree );
   //   b_publish( $tree, 'test/lib.o' );
   }
}

function read_options( $args ) {
   $options = array(
      'includes' => array(),
      'err_file' => false,
      'source_file' => '',
      'object_file' => '',
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