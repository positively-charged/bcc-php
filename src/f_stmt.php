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

define( 'MAX_MODULE_NAME_LENGTH', 8 );

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
   public $dec_for;
   public $func;
   public $case_stmt;
}

function f_create_tree( $options ) {
   $front = new front_t();
   $front->options = $options;
   $front->file = null;
   $front->files = array();
   $front->files_unloaded = array();
   $front->text = '';
   $front->text_pos = 0;
   $front->token = new token_t();
   $front->types = array();
   $front->module = new module_t();
   $front->scope = null;
   $front->scopes = array();
   $front->block = null;
   $front->bfunc_type = func_t::type_aspec;
   $front->reading_script_number = false;
   $front->dec_params = array();
   $front->tk_peeked = null;
   $front->importing = false;
   $front->func = null;
   $front->case_stmt = null;
   // Top scope.
   f_new_scope( $front );
   if ( ! f_load_file( $front, $options[ 'source_file' ] ) ) {
      printf( "error: failed to open file: %s\n", $options[ 'source_file' ] );
      return false;
   }
   f_read_tk( $front );

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
         f_read_tk( $front );
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
   $got_header = false;
   if ( $front->tk == tk_hash ) {
      $pos = $front->tk_pos;
      f_read_tk( $front );
      if ( $front->tk == tk_id && $front->tk_text == 'library' ) {
         f_read_tk( $front );
         f_test_tk( $front, tk_lit_string );
         if ( ! strlen( $front->tk_text ) ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos, 'module name is blank' );
         }
         else if ( strlen( $front->tk_text ) > MAX_MODULE_NAME_LENGTH ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $front->tk_pos,
               'library name too long (maximum length: %d characters)',
               MAX_MODULE_NAME_LENGTH );
         }
         else {
            $front->module->name = $front->tk_text;
         }
         f_read_tk( $front );
         $got_header = true;
      }
      else {
         f_read_dirc( $front, $pos );
      }
   }
   // Header required for an imported module.
   if ( $front->importing && ! $got_header ) {
      f_diag( $front, k_diag_err | k_diag_file, $front->tk_pos,
         'missing library name (#library "<name>") in imported module' );
   }
   while ( true ) {
      if ( $front->tk == tk_script ) {
         f_read_script( $front );
      }
      else if ( f_is_dec( $front ) ) {
         f_read_dec( $front, k_dec_top );
      }
      else if ( $front->tk == tk_hash ) {
         $pos = $front->tk_pos;
         f_read_tk( $front );
         f_read_dirc( $front, $pos );
      }
      else if ( $front->tk == tk_special ) {
         f_read_bfunc_list( $front );
      }
      else if ( $front->tk == tk_end ) {
         f_unload_file( $front );
         if ( $front->file === null ) {
            break;
         }
      }
      else {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $front->tk_pos, 'unexpected token: %s',
            $front->tk_text );
         f_read_tk( $front );
      }
   }
}

function f_read_dirc( $front, $pos ) {
   f_test_tk( $front, tk_id );
   if ( $front->tk_text == 'define' || $front->tk_text == 'libdefine' ) {
      f_read_tk( $front );
      $name = f_read_unique_name( $front );
      $expr = f_read_expr( $front );
      if ( $front->importing || $name[ 0 ] == 'l' ) {
         $constant = new constant_t();
         $constant->value = $expr->value;
         $constant->pos = $pos;
         $front->scope->names[ $name ] = $constant;
      }
   }
   else if ( $front->tk_text == 'include' ) {
      f_read_tk( $front );
      f_test_tk( $front, tk_lit_string );
      f_include_file( $front );
      f_read_tk( $front );
   }
   else if ( $front->tk_text == 'import' ) {
      f_read_tk( $front );
      f_test_tk( $front, tk_lit_string );
      // Modules imported by a module that is itself imported are not needed.
      if ( ! $front->importing ) {
         f_include_file( $front );
         f_read_module( $front );
      }
   }
   else if ( $front->tk_text == 'library' ) {
      f_read_tk( $front );
      f_test_tk( $front, tk_lit_string );
      f_read_tk( $front );
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $pos, 'library name between code' );
      f_diag( $front, k_diag_notice | k_diag_file, $pos,
         '#library must appear at the very top, before any other code' );
   }
   // Switch to the Big-E format.
   else if ( $front->tk_text == 'nocompact' ) {
      f_read_tk( $front );
      if ( ! $front->importing ) {
         $front->options[ 'format' ] = k_format_big_e;
      }
   }
   else if ( $front->tk_text == 'encryptstrings' ) {
      f_read_tk( $front );
      if ( ! $front->importing ) {
         $front->options[ 'encrypt_str' ] = true;
      }
   }
   else if (
      // NOTE: Not sure what these two are.
      $front->tk_text == 'wadauthor' ||
      $front->tk_text == 'nowadauthor' ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $pos, 'directive not supported: %s', $front->tk_text );
      f_read_tk( $front );
   }
   else {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $pos, 'unknown directive: %s', $front->tk_text );
      f_read_tk( $front );
   }
}

function f_include_file( $front ) {
   if ( $front->tk_text == '' ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'path is empty' );
      f_bail( $front );
   }
   else if ( ! f_load_file( $front, $front->tk_text ) ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'failed to load file: %s', $front->tk_text );
      f_bail( $front );
   }
}

function f_read_stmt( $front ) {
   switch ( $front->tk ) {
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

function f_read_block( $front ) {
   f_test_tk( $front, tk_brace_l );
   f_read_tk( $front );
   while ( true ) {
      if ( f_is_dec( $front ) ) {
         f_read_dec( $front, k_dec_local );
      }
      else if ( $front->tk == tk_brace_r ) {
         f_read_tk( $front );
         break;
      }
      else {
         f_read_stmt( $front );
      }
   }
}

function f_read_if( $front ) {
   f_test_tk( $front, tk_if );
   f_read_tk( $front );
   f_test_tk( $front, tk_paren_l );
   f_read_tk( $front );
   $expr = f_read_expr( $front );
   f_test_tk( $front, tk_paren_r );
   f_read_tk( $front );
   $body = new block_t();
   $body->prev = $front->block;
   $front->block = $body;
   f_read_stmt( $front, $body );
   $else_body = null;
   if ( $front->tk == tk_else ) {
      f_read_tk( $front );
      $body_else = new body_t();
      f_read_stmt( $front );
      $body_else->prev = $parent;
      f_read_stmt( $front, $body );
   }
   $stmt = new if_t();
   $stmt->expr = $expr;
   $stmt->body = $body;
   $stmt->else_body = $else_body;
   array_push( $body->prev->stmts, $stmt );
   $front->block = $body->prev;
}

function f_read_switch( $front ) {
   f_test_tk( $front, tk_switch );
   f_read_tk( $front );
   f_test_tk( $front, tk_paren_l );
   f_read_tk( $front );
   $stmt = new switch_t();
   $stmt->cond = f_read_expr( $front );
   f_test_tk( $front, tk_paren_r );
   f_read_tk( $front );
   $prev = $front->case_stmt;
   $front->case_stmt = $stmt;
   $body = new block_t();
   $body->in_switch = true;
   $body->prev = $front->block;
   $front->block = $body;
   f_new_scope( $front );
   f_read_stmt( $front );
   f_pop_scope( $front );
   $front->block = $body->prev;
   $front->case_stmt = $prev;
   $stmt->body = $body;
   array_push( $front->block->stmts, $stmt );
}

function f_read_case( $front ) {
   $pos = $front->tk_pos;
   $block = $front->block;
   while ( $block && ! $block->in_switch ) {
      $block = $block->prev;
   }
   if ( ! $block ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $pos, 'case outside switch statement' );
   }
   else if ( $block != $front->block ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $pos, 'case inside a nested statement' );
   }
   $expr = null;
   if ( $front->tk == tk_case ) {
      f_read_tk( $front );
      $expr = f_read_expr( $front );
   }
   else {
      f_test_tk( $front, tk_default );
      f_read_tk( $front );
   }
   f_test_tk( $front, tk_colon );
   f_read_tk( $front );
   if ( $expr ) {
      if ( ! $expr->folded ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $expr->pos, 'case value not constant' );
         f_bail( $front );
      }
      // No duplicate cases allowed.
      foreach ( $front->case_stmt->cases as $stmt ) {
         if ( $stmt->expr && $stmt->expr->value == $expr->value ) {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $pos, 'case with value %d duplicated',
               $expr->value );
            f_diag( $front, k_diag_file | k_diag_line | k_diag_column,
               $stmt->pos, 'previous case found here' );
         }
      }
   }
   else {
      // There should only be a single default case.
      if ( $front->case_stmt->default_case ) {
         f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
            k_diag_column, $pos, 'default case duplicated' );
         f_diag( $front, k_diag_file | k_diag_line | k_diag_column,
            $front->case_stmt->default_case->pos,
            'previous default case found here' );
         f_bail( $front );
      }
   }
   $stmt = new case_t();
   $stmt->expr = $expr;
   $stmt->pos = $pos;
   array_push( $front->block->stmts, $stmt );
   array_push( $front->case_stmt->cases, $stmt );
   if ( $expr ) {
      $prev = null;
      $curr = $front->case_stmt->case_head;
      while ( $curr && $curr->expr->value < $expr->value ) {
         $prev = $curr;
         $curr = $curr->next_sorted;
      }
      if ( $prev ) {
         $stmt->next_sorted = $prev->next_sorted;
         $prev->next_sorted = $stmt;
      }
      else {
         $stmt->next_sorted = $front->case_stmt->case_head_sorted;
         $front->case_stmt->case_head_sorted = $stmt;
      }
   }
   else {
      $front->case_stmt->default_case = $stmt;
   }
}

function f_read_while( $front ) {
   if ( $front->tk == tk_do ) {
      f_read_tk( $front );
      $body = new block_t();
      $body->in_loop = true;
      $body->prev = $front->block;
      $front->block = $body;
      f_new_scope( $front );
      f_read_stmt( $front );
      f_pop_scope( $front );
      $front->block = $body->prev;
      $type = while_t::type_do_while;
      if ( $front->tk == tk_until ) {
         $type = while_t::type_do_until;
         f_read_tk( $front );
      }
      else {
         f_test_tk( $front, tk_while );
         f_read_tk( $front );
      }
      f_test_tk( $front, tk_paren_l );
      f_read_tk( $front );
      $expr = f_read_expr( $front );
      f_test_tk( $front, tk_paren_r );
      f_read_tk( $front );
      f_test_tk( $front, tk_semicolon );
      f_read_tk( $front );
      $stmt = new while_t();
      $stmt->type = $type;
      $stmt->expr = $expr;
      $stmt->body = $body;
      array_push( $body->prev->stmts, $stmt );
   }
   else {
      $type = while_t::type_while;
      if ( $front->tk == tk_until ) {
         $type = while_t::type_until;
         f_read_tk( $front );
      }
      else {
         f_test_tk( $front, tk_while );
         f_read_tk( $front );
      }
      f_test_tk( $front, tk_paren_l );
      f_read_tk( $front );
      $expr = f_read_expr( $front );
      f_test_tk( $front, tk_paren_r );
      f_read_tk( $front );
      $body = new block_t();
      $body->in_loop = true;
      $body->prev = $front->block;
      $front->block = $body;
      f_new_scope( $front );
      f_read_stmt( $front );
      f_pop_scope( $front );
      $front->block = $body->prev;
      $stmt = new while_t();
      $stmt->type = $type;
      $stmt->expr = $expr;
      $stmt->body = $body;
      array_push( $body->prev->stmts, $stmt );
   }
}

function f_read_for( $front ) {
   f_test_tk( $front, tk_for );
   f_read_tk( $front );
   f_test_tk( $front, tk_paren_l );
   f_read_tk( $front );
   f_new_scope( $front );
   // Optional initialization.
   $dec = null;
   $init = null;
   if ( $front->tk != tk_semicolon ) {
      if ( f_is_dec( $front ) ) {
         $front->dec_for = array();
         f_read_dec( $front, k_dec_for );
         $dec = $front->dec_for;
         $front->dec_for = null;
      }
      else {
         $init = array();
         while ( true ) {
            $expr = f_read_expr( $front );
            array_push( $init, $expr );
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
   else {
      f_read_tk( $front );
   }
   // Optional condition.
   $cond = null;
   if ( $front->tk != tk_semicolon ) {
      $cond = f_read_expr( $front );
      f_test_tk( $front, tk_semicolon );
      f_read_tk( $front );
   }
   else {
      f_read_tk( $front );
   }
   // Optional post-expression.
   $post = null;
   if ( $front->tk != tk_paren_r ) {
      $post = f_read_expr( $front );
   }
   f_test_tk( $front, tk_paren_r );
   f_read_tk( $front );
   $body = new block_t();
   $body->in_loop = true;
   $body->prev = $front->block;
   $front->block = $body;
   f_read_stmt( $front );
   $front->block = $body->prev;
   f_pop_scope( $front );
   $stmt = new for_t();
   $stmt->init = $init;
   if ( $dec ) {
      $stmt->init = $dec;
   }
   $stmt->cond = $cond;
   $stmt->post = $post;
   $stmt->body = $body;
   array_push( $front->block->stmts, $stmt );
}

function f_read_jump( $front ) {
   if ( $front->tk == tk_break ) {
      $pos = $front->tk_pos;
      f_read_tk( $front );
      f_test_tk( $front, tk_semicolon );
      f_read_tk( $front );
      $block = $front->block;
      while ( true ) {
         if ( $block ) {
            if ( $block->in_loop || $block->in_switch ) {
               $stmt = new jump_t();
               array_push( $front->block->stmts, $stmt );
               break;
            }
            else {
               $block = $block->prev;
            }
         }
         else {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $pos, 'break outside loop or switch' );
            break;
         }
      }
   }
   else {
      f_test_tk( $front, tk_continue );
      $pos = $front->tk_pos;
      f_read_tk( $front );
      f_test_tk( $front, tk_semicolon );
      f_read_tk( $front );
      $block = $front->block;
      while ( true ) {
         if ( $block ) {
            if ( $block->in_loop ) {
               $stmt = new jump_t();
               $stmt->type = jump_t::type_continue;
               array_push( $front->block->stmts, $stmt );
               break;
            }
            else {
               $block = $block->prev;
            }
         }
         else {
            f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
               k_diag_column, $pos, 'continue outside loop' );
            break;
         }
      }
   }
}

function f_read_script_jump( $front ) {
   $type = script_jump_t::terminate;
   switch ( $front->tk ) {
   case tk_suspend:
      $type = script_jump_t::suspend;
      break;
   case tk_restart:
      $type = script_jump_t::restart;
      break;
   default:
      f_test_tk( $front, tk_terminate );
      break;
   }
   if ( $front->block->in_script ) {
      $jump = new script_jump_t();
      $jump->type = $type;
      array_push( $front->block->stmts, $jump );
   }
   else {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $front->tk_pos, 'script-jump statement outside script' );
   }
   f_read_tk( $front );
   f_test_tk( $front, tk_semicolon );
   f_read_tk( $front );
}

function f_read_return( $front ) {
   $pos = $front->tk_pos;
   f_test_tk( $front, tk_return );
   f_read_tk( $front );
   $expr = null;
   if ( $front->tk != tk_semicolon ) {
      $expr = f_read_expr( $front );
   }
   f_test_tk( $front, tk_semicolon );
   f_read_tk( $front );
   if ( ! $front->func ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line | k_diag_column,
         $pos, 'return statement outside a function' );
   }
   else if ( $front->func->value && ! $expr ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         k_diag_column, $pos,
         'return statement missing return value' );
   }
   else if ( $expr && ! $front->func->value ) {
      f_diag( $front, k_diag_err | k_diag_file | k_diag_line |
         k_diag_column, $expr->pos, 'returning value in void function' );
   }
   else {
      $stmt = new return_t();
      $stmt->expr = $expr;
      array_push( $front->block->stmts, $stmt );
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
   if ( $front->tk == tk_semicolon ) {
      f_read_tk( $front );
   }
   else {
      $expr = f_read_expr( $front );
      f_test_tk( $front, tk_semicolon );
      f_read_tk( $front );
      array_push( $front->block->stmts, $expr );
   }
}