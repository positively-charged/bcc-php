<?php

class back_t {
   public $tree;
   public $file;
   public $packed;
   public function __construct() {
      $this->packed = '';
   }
}

function b_publish( $tree, $path ) {
   $back = new back_t();
   $back->tree = $tree;
   $back->file = fopen( $path, 'wb' );
   fwrite( $back->file, 'ACSE' );
   fwrite( $back->file, pack( 'l', 0 ) );
   foreach ( $tree[ 'module' ]->scripts as $script ) {
      b_do_script( $back, $script );
      $script->offset = ftell( $back->file );
      fwrite( $back->file, $back->packed );
      $back->packed = '';
   }
   $offset = ftell( $back->file );
   b_do_sptr( $back );
   b_do_sflg( $back );
   fseek( $back->file, 4 );
   fwrite( $back->file, pack( 'l', $offset ) );
   fclose( $back->file );
}

function b_do_sptr( $back ) {
   $packed = '';
   foreach ( $back->tree[ 'module' ]->scripts as $script ) {
      $packed .= pack( 'ssll',
         $script->number,
         $script->type,
         $script->offset,
         count( $script->params->vars ) );
   }
   if ( $packed != '' ) {
      fwrite( $back->file, 'SPTR' );
      fwrite( $back->file, pack( 'l', strlen( $packed ) ) );
      fwrite( $back->file, $packed, strlen( $packed ) );
   }
}

function b_do_sflg( $back ) {
   $packed = '';
   foreach ( $back->tree[ 'module' ]->scripts as $script ) {
      if ( $script->flags ) {
         $packed .= pack( 'ss',
            $script->number,
            $script->flags );
      }
   }
   if ( $packed != '' ) {
      fwrite( $back->file, 'SFLG' );
      fwrite( $back->file, pack( 'l', strlen( $packed ) ) );
      fwrite( $back->file, $packed, strlen( $packed ) );
   }
}