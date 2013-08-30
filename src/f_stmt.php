<?php

define( 'k_dec_top', 0 );
define( 'k_dec_local', 1 );
define( 'k_dec_for', 2 );
define( 'k_dec_param', 3 );

define( 'k_diag_file', 0x1 );
define( 'k_diag_line', 0x2 );
define( 'k_diag_column', 0x4 );
define( 'k_diag_notice', 0x10 );
define( 'k_diag_warning', 0x20 );
define( 'k_diag_err', 0x40 );

class front_t {
   public $options;
   public $file;
   public $files;
   public $files_unloaded;
   public $ch;
   public $importing;
   public $types;
   public $scope;
   public $scopes;
   public $module;
   public $block;
   public $bfunc_type;
   public $tk;
   public $tk_pos;
   public $tk_text;
   public $tk_peeked;
   public $reading_script_number;
   public $dec_params;
   public function __construct( $options ) {
      $this->options = $options;
      $this->file = null;
      $this->files = array();
      $this->files_unloaded = array();
      $this->text = '';
      $this->text_pos = 0;
      $this->token = new token_t();
      $this->types = array();
      $this->module = new module_t();
      $this->scope = null;
      $this->scopes = array();
      $this->block = null;
      $this->bfunc_type = func_t::type_aspec;
      $this->reading_script_number = false;
      $this->dec_params = array();
      $this->tk_peeked = null;
   }
}

function f_new_scope( $front ) {
   $scope = new scope_t();
   if ( count( $front->scopes ) > 1 ) {
      $scope->index = $front->scope->index;
      $scope->index_high = $front->scope->index_high;
   }
   array_push( $front->scopes, $scope );
   $front->scope = $scope;
}

function f_pop_scope( $front ) {
   $scope = array_pop( $front->scopes );
   $front->scope = end( $front->scopes );
   if ( count( $front->scopes ) > 1 &&
      $scope->index_high > $front->scope->index_high ) {
      $front->scope->index_high = $scope->index_high;
   }
}

function f_alloc_index( $front ) {
   $index = $front->scope->index;
   $front->scope->index += 1;
   if ( $front->scope->index > $front->scope->index_high ) {
      $front->scope->index_high += 1;
   }
   return $index;
}

function f_find_name( $front, $name ) {
   for ( $i = count( $front->scopes ) - 1; $i >= 0; $i -= 1 ) {
      if ( isset( $front->scopes[ $i ]->names[ $name ] ) ) {
         return $front->scopes[ $i ]->names[ $name ];
      }
   }
   return null;
}

function f_create_tree( $options ) {
   $front = new front_t( $options );
   // Top scope.
   f_new_scope( $front );
   if ( ! f_load_file( $front, $options[ 'source_file' ] ) ) {
      printf( "error: failed to open file: %s\n", $options[ 'source_file' ] );
      return false;
   }
   f_read_token( $front );

   $list = array();
   $type = new type_t( 'int' );
   $list[ $type->name ] = $type;
   $type = new type_t( 'str' );
   $list[ $type->name ] = $type;
   $type = new type_t( 'bool' );
   $list[ $type->name ] = $type;
   $front->types = $list;

   try {
      f_read_module( $front );
      return array(
         'module' => $front->module
      );
   }
   catch ( Exception $e ) {
      //printf( "Compilation error\n" );
      return false;
   }

/*

   print_r( f_read_expr( $front ) );
   return false;

   try {
      while ( true ) {
         f_read_token( $front );
         echo $front->token->text, " ", $front->token->type, "\n";
         if ( $front->token->type === tk_end ) {
            break;
         }
      }
   }
   catch ( Exception $e ) {}


   while ( true ) {
      if ( $front->ch != "\0" ) {
         echo $front->text_line, ':', $front->text_column, ' ', $front->ch, "\n";
         f_read_ch( $front );
      }
      else {
         break;
      }
   }
*/
}

function f_skip( $front, $expected ) {
   f_test( $front, $expected );
   f_read_token( $front );
}

function f_test( $front, $expected ) {
   if ( $front->tk != $expected ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'unexpected token: %s', $front->tk_text );
      f_bail( $front );
   }
}

function f_test_tk( $front, $expected ) {
   if ( $front->tk != $expected ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'unexpected token: %s', $front->tk_text );
      f_bail( $front );
   }
}

function f_bail( $front ) {
   throw new Exception();
}

function f_diag( $front, $flags ) {
   $args = func_get_args();
   array_shift( $args );
   array_shift( $args );
   if ( $flags & k_diag_file ) {
      $pos = array_shift( $args );
      echo $pos[ 'file' ]->path;
      if ( $flags & k_diag_line ) {
         echo ':', $pos[ 'line' ];
         if ( $flags & k_diag_column ) {
            echo ':', $pos[ 'column' ];
         }
      }
      echo ': ';
   }
   switch ( $flags & 0x70 ) {
   case k_diag_err:
      echo 'error: ';
      break;
   case k_diag_warning:
      echo 'warning: ';
      break;
   case k_diag_notice:
      echo 'notice: ';
      break;
   default:
      break;
   }
   $msg = array_shift( $args );
   vprintf( $msg, $args );
   echo "\n";
}

function f_read_module( $front ) {
   while ( true ) {
      if ( $front->tk == tk_end ) {
         f_unload_file( $front );
         if ( $front->file === null ) {
            break;
         }
      }
      else if ( $front->tk == tk_hash ) {
         f_read_dirc( $front );
      }
      else if ( $front->tk == tk_script ) {
         f_read_script( $front );
      }
      else if ( f_is_dec( $front ) ) {
         f_read_dec( $front, k_dec_top );
      }
      else {
         f_bail( $front );
      }
   }
}

function f_read_dirc( $front ) {
   $pos = $front->tk_pos;
   f_read_token( $front );
   f_test( $front, tk_id );
   $name = $front->tk_text;
   $define = false;
   $define_lib = false;
   if ( $name == 'define' ) {
      $define = true;
   }
   else if ( $name == 'libdefine' ) {
      $define = true;
      $define_lib = true;
   }
   if ( $define ) {
      f_read_token( $front );
      $name = f_read_unique_name( $front );
      $expr = f_read_expr( $front );
      $constant = new constant_t();
      $constant->value = $expr[ 'value' ];
      $constant->pos = $pos;
      $front->scope->names[ $name ] = $constant;
   }
   else if ( $name == 'include' ) {
      f_drop( $front );
      f_test( $front, tk_lit_string );
      f_include_file( $front, $front->token->text );
      f_drop( $front );
   }
   else if ( $name == 'libinclude' ) {
   }
   else if ( $name == 'import' ) {
   }
   else if ( $name == 'library' ) {
   }
   else if ( $name == 'nocompact' ) {
   }
   else if ( $name == 'encryptstrings' ) {
   }
   else if ( $name == 'bfunc' ) {
      f_read_token( $front );
      f_test( $front, tk_lit_string );
      switch ( $front->tk_text ) {
      case 'aspec':
         $front->bfunc_type = func_t::type_aspec;
         break;
      case 'ext':
         $front->bfunc_type = func_t::type_ext;
         break;
      case 'ded':
         $front->bfunc_type = func_t::type_ded;
         break;
      case 'format':
         $front->bfunc_type = func_t::type_format;
         break;
      default:
         f_diag( $front, array(
            'type' => 'err',
            'msg' => 'unknown builtin-function type',
            'file' => $front->file->path,
            'line' => $front->file->line,
            'column' => $front->file->column ) );
         f_bail( $front );
      }
      f_read_token( $front );
   }
   else {

   }
}

function f_include_file( $front, $path ) {
   if ( $path != '' ) {
      if ( ! f_load_file( $front, $path ) ) {
         f_diag( $front, array( 'type' => 'err',
            'msg' => 'failed to load file: %s', 'args' => array( $path ) ) );
         f_bail( $front );
      }
   }
   else {
      f_diag( $front, array( 'type' => 'err', 'msg' => 'path is empty' ) );
      f_bail( $front );
   }
}

function f_read_block( $front, $block ) {
   f_skip( $front, tk_brace_l );
   $prev = $front->block;
   $front->block = $block;
   while ( true ) {
      if ( f_is_dec( $front ) ) {
         f_read_dec( $front, k_dec_local );
      }
      else if ( f_tk( $front ) == tk_brace_r ) {
         f_drop( $front );
         break;
      }
      else {
         f_read_stmt( $front );
      }
   }
   $front->block = $prev;
}

function f_read_stmt( $front ) {
   switch ( f_tk( $front ) ) {
   case tk_brace_l:
      f_new_scope( $front );
      f_read_block( $front );
      f_pop_scope( $front );
      break;
   case tk_if:
      f_read_if( $front );
      break;
   case tk_switch:
      f_read_switch( $front );
      break;
   case tk_case:
   case tk_default:
      f_read_case( $front );
      break;
   case tk_while:
   case tk_until:
   case tk_do:
      f_read_while( $front );
      break;
   case tk_for:
      f_read_for( $front );
      break;
   case tk_break:
   case tk_continue:
      f_read_jump( $front );
      break;
   case tk_terminate:
   case tk_suspend:
   case tk_restart:
      f_read_script_jump( $front );
      break;
   case tk_return:
      f_read_return( $front );
      break;
   case tk_paltrans:
      f_read_paltrans( $front );
      break;
   default:
      f_read_expr_stmt( $front );
      break;
   }
}

function f_read_if( $front ) {
   f_skip( $front, tk_if );
   f_skip( $front, tk_paren_l );
   f_read_expr( $front );
   f_skip( $front, tk_paren_r );
   f_read_stmt( $front );
   if ( f_tk( $front ) == tk_else ) {
      f_drop( $front );
      f_read_stmt( $front );
   }
}

function f_read_switch( $front ) {
   f_skip( $front, tk_switch );
   f_skip( $front, tk_paren_l );
   f_read_expr( $front );
   f_skip( $front, tk_paren_r );
   f_read_stmt( $front );
}

function f_read_case( $front ) {
   if ( f_tk( $front ) == tk_case ) {
      f_drop( $front );
      f_read_expr( $front );
   }
   else {
      f_skip( $front, tk_default );
   }
   f_skip( $front, tk_colon );
}

function f_read_while( $front ) {
   $is_do = false;
   if ( f_tk( $front ) == tk_while ) {
      f_drop( $front );
   }
   else if ( f_tk( $front ) == tk_until ) {
      f_drop( $front );
   }
   else {
      f_skip( $front, tk_do );
      $is_do = true;
   }
   if ( ! $is_do ) {
      f_skip( $front, tk_paren_l );
      f_read_expr( $front );
      f_skip( $front, tk_paren_r );
   }
   f_read_stmt( $front );
   if ( $is_do ) {
      if ( f_tk( $front ) == tk_until ) {
         f_drop( $front );
      }
      else {
         f_skip( $front, tk_while );
      }
      f_skip( $front, tk_paren_l );
      f_read_expr( $front );
      f_skip( $front, tk_paren_r );
      f_skip( $front, tk_semicolon );
   }
}

function f_read_for( $front ) {
   f_skip( $front, tk_for );
   f_skip( $front, tk_paren_l );
   // Optional initialization.
   if ( f_tk( $front ) != tk_semicolon ) {
      if ( f_is_dec( $front ) ) {
         f_read_dec( $front, k_dec_for );
      }
      else {
         f_read_expr( $front );
         f_skip( $front, tk_semicolon );
      }
   }
   else {
      f_drop( $front );
   }
   // Optional condition.
   if ( f_tk( $front ) != tk_semicolon ) {
      f_read_expr( $front );
      f_skip( $front, tk_semicolon );
   }
   else {
      f_drop( $front );
   }
   // Optional post-expression.
   if ( f_tk( $front ) != tk_paren_r ) {
      f_read_expr( $front );
   }
   f_skip( $front, tk_paren_r );
   f_read_stmt( $front );
}

function f_read_jump( $front ) {
   if ( f_tk( $front ) == tk_break ) {
      f_drop( $front );
      f_skip( $front, tk_semicolon );
   }
   else {
      f_skip( $front, tk_continue );
      f_skip( $front, tk_semicolon );
   }
}

function f_read_script_jump( $front ) {
   $type = script_jump_t::terminate;
   switch ( f_tk( $front ) ) {
   case tk_suspend:
      $type = script_jump_t::suspend;
      f_drop( $front );
      break;
   case tk_restart:
      $type = script_jump_t::restart;
      f_drop( $front );
      break;
   default:
      f_skip( $front, tk_terminate );
      break;
   }
   f_skip( $front, tk_semicolon );
   $jump = new script_jump_t();
   $jump->type = $type;
   array_push( $front->block->stmts, $jump );
}

function f_read_return( $front ) {
   f_drop( $front );
   if ( f_tk( $front ) == tk_semicolon ) {
      f_drop( $front );
   }
   else {
      f_read_expr( $front );
      f_skip( $front, tk_semicolon );
   }
}

function f_read_paltrans( $front ) {
   f_skip( $front, tk_paltrans );
   f_skip( $front, tk_paren_l );
   f_read_expr( $front );
   while ( f_tk( $front ) == tk_comma ) {
      f_drop( $front );
      f_read_expr( $front );
      f_skip( $front, tk_colon );
      f_read_expr( $front );
      f_skip( $front, tk_assign );
      if ( f_tk( $front ) == tk_bracket_l ) {
         f_read_palrange_rgb_field( $front );
         f_skip( $front, tk_colon );
         f_read_palrange_rgb_field( $front );
      }
      else {
         f_read_expr( $front );
         f_skip( $front, tk_colon );
         f_read_expr( $front );
      }
   }
   f_skip( $front, tk_paren_r );
   f_skip( $front, tk_semicolon );
}

function f_read_palrange_rgb_field( $front ) {
   f_skip( $front, tk_bracket_l );
   f_read_expr( $front );
   f_skip( $front, tk_comma );
   f_read_expr( $front );
   f_skip( $front, tk_comma );
   f_read_expr( $front );
   f_skip( $front, tk_bracket_r );
}

function f_read_expr_stmt( $front ) {
   if ( f_tk( $front ) == tk_semicolon ) {
      f_drop( $front );
   }
   else {
      $expr = f_read_expr( $front );
      f_skip( $front, tk_semicolon );
      array_push( $front->block->stmts, $expr[ 'node' ] );
   }
}