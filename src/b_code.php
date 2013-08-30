<?php

function b_pcode( $back, $code ) {
   $back->packed .= pack( 'l', $code );
   $args = func_get_args();
   array_shift( $args );
   array_shift( $args );
   foreach ( $args as $arg ) {
      if ( is_array( $arg ) ) {
         foreach ( $arg as $element ) {
            $back->packed .= pack( 'l', $element );
         }
      }
      else {
         $back->packed .= pack( 'l', $arg );
      }
   }
}