<?php

define( 'tk_end', 0 );
define( 'tk_hash', 1 );
define( 'tk_bracket_l', 2 );
define( 'tk_bracket_r', 3 );
define( 'tk_paren_l', 4 );
define( 'tk_paren_r', 5 );
define( 'tk_brace_l', 6 );
define( 'tk_brace_r', 7 );
define( 'tk_dot', 8 );
define( 'tk_inc', 9 ); 
define( 'tk_dec', 10 );
define( 'tk_id', 11 );
define( 'tk_comma', 12 );
define( 'tk_colon', 13 );
define( 'tk_semicolon', 14 );
define( 'tk_assign', 15 );
define( 'tk_assign_add', 16 );
define( 'tk_assign_sub', 17 );
define( 'tk_assign_mul', 18 );
define( 'tk_assign_div', 19 );
define( 'tk_assign_mod', 20 );
define( 'tk_assign_shift_l', 21 );
define( 'tk_assign_shift_r', 22 );
define( 'tk_assign_bit_and', 23 );
define( 'tk_assign_bit_xor', 24 );
define( 'tk_assign_bit_or', 25 );
define( 'tk_eq', 26 );
define( 'tk_neq', 27 );
define( 'tk_log_not', 28 );
define( 'tk_log_and', 29 );
define( 'tk_log_or', 30 );
define( 'tk_bit_and', 31 );
define( 'tk_bit_or', 32 );
define( 'tk_bit_xor', 33 );
define( 'tk_bit_not', 34 );
define( 'tk_lt', 35 );
define( 'tk_lte', 36 );
define( 'tk_gt', 37 );
define( 'tk_gte', 38 );
define( 'tk_plus', 39 );
define( 'tk_minus', 40 );
define( 'tk_slash', 41 );
define( 'tk_star', 42 );
define( 'tk_mod', 43 );
define( 'tk_shift_l', 44 );
define( 'tk_shift_r', 45 );
define( 'tk_question', 46 );
define( 'tk_break', 47 );
define( 'tk_case', 48 );
define( 'tk_const', 49 );
define( 'tk_continue', 50 );
define( 'tk_default', 51 );
define( 'tk_do', 52 );
define( 'tk_else', 53 );
define( 'tk_enum', 54 );
define( 'tk_for', 55 );
define( 'tk_if', 56 );
define( 'tk_int', 57 );
define( 'tk_return', 58 );
define( 'tk_static', 59 );
define( 'tk_str', 60 );
define( 'tk_struct', 61 );
define( 'tk_switch', 62 );
define( 'tk_void', 63 );
define( 'tk_while', 64 );
define( 'tk_bool', 65 );
define( 'tk_lit_decimal', 66 );
define( 'tk_lit_octal', 67 );
define( 'tk_lit_hex', 68 );
define( 'tk_lit_binary', 69 );
define( 'tk_lit_fixed', 70 );
define( 'tk_lit_string', 71 );
define( 'tk_lit_char', 72 );
define( 'tk_paltrans', 73 );
define( 'tk_global', 74 );
define( 'tk_script', 75 );
define( 'tk_until', 76 );
define( 'tk_world', 77 );
define( 'tk_open', 78 );
define( 'tk_respawn', 79 );
define( 'tk_death', 80 );
define( 'tk_enter', 81 );
define( 'tk_pickup', 82 );
define( 'tk_blue_return', 83 );
define( 'tk_red_return', 84 );
define( 'tk_white_return', 85 );
define( 'tk_lightning', 86 );
define( 'tk_disconnect', 87 );
define( 'tk_unloading', 88 );
define( 'tk_clientside', 89 );
define( 'tk_net', 90 );
define( 'tk_restart', 91 );
define( 'tk_suspend', 92 );
define( 'tk_terminate', 93 );
define( 'tk_function', 94 );
define( 'tk_special', 95 );

class token_t {
   public $type;
   public $text;
   public function __construct() {
      $this->type = tk_end;
      $this->text = '';
   }
}

function f_load_file( $front, $user_path ) {
   $direct = false;
   $includes = $front->options[ 'includes' ];
   $includes_i = 0;
   while ( true ) {
      if ( ! $direct ) {
         $path = $user_path;
         $direct = true;
      }
      else if ( $includes_i < count( $includes ) ) {
         $path = $includes[ $includes_i ] . '/' . $user_path;
         $includes_i += 1;
      }
      else {
         return false;
      }
      $path = realpath( $path );
      if ( $path ) {
         $text = @file_get_contents( $path );
         if ( $text !== false ) {
            $file = new file_t();
            $file->path = $user_path;
            $file->load_path = $path;
            $file->length = strlen( $text );
            $file->text = $text . "\0";
            $file->pos = 0;
            $file->line = 1;
            $file->column = 0;
            $file->ch = '';
            if ( $front->file !== null ) {
               $front->file->ch = $front->ch;
               array_push( $front->files, $front->file );
            }
            $front->file = $file;
            $front->ch = $text[ 0 ];
            return true;
         }
      }
   }
}

function f_unload_file( $front ) {
   if ( count( $front->files ) ) {
      array_push( $front->files_unloaded, $front->file );
      $front->file = array_pop( $front->files );
      $front->ch = $front->file->ch;
      f_read_tk( $front );
   }
   else {
      $front->file = null;
   }
}

function f_read_ch( $front ) {
   $pos = $front->file->pos + 1;
   if ( $pos < $front->file->length ) {
      if ( $front->ch == "\n" ) {
         $front->file->line += 1;
         $front->file->column = 0;
      }
      else {
         $front->file->column += 1;
      }
      $front->ch = $front->file->text[ $pos ];
      $front->file->pos = $pos;
   }
   else {
      $front->ch = '';
      $front->file->pos = $front->file->length;
   }
}

function f_peek_ch( $front ) {
   $pos = $front->file->pos + 1;
   if ( $pos < $front->file->length ) {
      return $front->file->text[ $pos ];
   }
   else {
      return '';
   }
}

function f_read_tk( $front ) {
   if ( $front->tk_peeked ) {
      list(
         $front->tk,
         $front->tk_pos,
         $front->tk_text
      ) = $front->tk_peeked;
      $front->tk_peeked = null;
   }
   else {
      list( 
         $front->tk,
         $front->tk_pos,
         $front->tk_text
      ) = f_token_read( $front );
   }
}

function f_peek_tk( $front ) {
   if ( $front->tk_peeked === null ) {
      $front->tk_peeked = f_token_read( $front );
   }
   return $front->tk_peeked[ 0 ];
}

function f_token_read( $front ) {
   $type = tk_end;
   $text = '';

   state_start:
   // --------------------------------------------------------------------
   while ( ctype_space( $front->ch ) ) {
      f_read_ch( $front );
   }
   $line = $front->file->line;
   $column = $front->file->column;
   $pos_s = $front->file->pos;
   $pos_e = 0;
   if ( ctype_alpha( $front->ch ) || $front->ch == '_' ) {
      goto state_id;
   }
   else if ( $front->ch == '0' ) {
      f_read_ch( $front );
      goto state_zero;
   }
   else if ( $front->ch >= '1' && $front->ch <= '9' ) {
      goto state_decimal;
   }
   else {
      switch ( $front->ch ) {
      case '"':
         f_read_ch( $front );
         goto state_string;
      case '\'':
         f_read_ch( $front );
         goto state_char;
      case '=':
         f_read_ch( $front );
         goto state_assign;
      case '!':
         f_read_ch( $front );
         goto state_exclamation;
      case '+':
         f_read_ch( $front );
         goto state_add;
      case '-':
         f_read_ch( $front );
         goto state_sub;
      case '*':
         f_read_ch( $front );
         goto state_star;
      case '/':
         f_read_ch( $front );
         goto state_slash;
      case '%':
         f_read_ch( $front );
         goto state_percent;
      case '<':
         f_read_ch( $front );
         goto state_angleb_l;
      case '>':
         f_read_ch( $front );
         goto state_angleb_r;
      case '&':
         f_read_ch( $front );
         goto state_ampersand;
      case '|':
         f_read_ch( $front );
         goto state_pipe;
      case '^':
         f_read_ch( $front );
         goto state_caret;
      case ';':
         f_read_ch( $front );
         $type = tk_semicolon;
         goto state_finish;
      case ',':
         f_read_ch( $front );
         $type = tk_comma;
         goto state_finish;
      case '(':
         f_read_ch( $front );
         $type = tk_paren_l;
         goto state_finish;
      case ')':
         f_read_ch( $front );
         $type = tk_paren_r;
         goto state_finish;
      case '[':
         f_read_ch( $front );
         $type = tk_bracket_l;
         goto state_finish;
      case ']':
         f_read_ch( $front );
         $type = tk_bracket_r;
         goto state_finish;
      case '{':
         f_read_ch( $front );
         $type = tk_brace_l;
         goto state_finish;
      case '}':
         f_read_ch( $front );
         $type = tk_brace_r;
         goto state_finish;
      case ':':
         f_read_ch( $front );
         $type = tk_colon;
         goto state_finish;
      case '~':
         f_read_ch( $front );
         $type = tk_bit_not;
         goto state_finish;
      case '#':
         f_read_ch( $front );
         $type = tk_hash;
         goto state_finish;
      case '':
         $type = tk_end;
         goto state_finish;
      default:
         f_diag( $front, 0, 'invalid character \'%s\'', $front->ch );
         f_read_ch( $front );
         goto state_start;
      }
   }

   state_string:
   // --------------------------------------------------------------------
   while ( true ) {
      if ( $front->ch == '' ) {
      }
      else if ( $front->ch == '"' ) {
         f_read_ch( $front );
         break;
      }
      else {
         f_read_ch( $front );
      }
   }
   $pos_s += 1;
   $pos_e = $front->file->pos - 1;
   $type = tk_lit_string;
   goto state_finish;

   state_char:
   // --------------------------------------------------------------------
   if ( $front->ch == '\\' ) {

   }
   else if ( $front->ch == '\'' || $front->ch == '' ) {
      f_diag( $front, array(
         'type' => 'err',
         'msg' => 'missing character in character literal',
         'file' => $front->file->path,
         'line' => $front->file->line,
         'column' => $front->file->column
      ) );
      f_bail( $front );
   }
   else {
      f_read_ch( $front );
      if ( $front->ch == '\'' ) {
         $type = tk_lit_char;
         $pos_s += 1;
         $pos_e = $front->file->pos;
         f_read_ch( $front );
         goto state_finish;
      }
      else {
         f_diag( $front, array( 'type' => 'err',
            'msg' => 'multiple characters in character literal',
            'file' => $front->file->path,
            'line' => $front->file->line,
            'column' => $front->file->column
         ) );
         f_bail( $front );
      }
   }

   state_id:
   // --------------------------------------------------------------------
   while ( ctype_alnum( $front->ch ) || $front->ch == '_' ) {
      f_read_ch( $front );
   }
   $id = substr( $front->file->text, $pos_s, $front->file->pos - $pos_s );
   $id = strtolower( $id );
   $reserved = array(
      'bluereturn' => tk_blue_return,
      'bool' => tk_bool,
      'break' => tk_break,
      'case' => tk_case,
      'clientside' => tk_clientside,
      'const' => tk_const,
      'continue' => tk_continue,
      'createtranslation' => tk_paltrans,
      'death' => tk_death,
      'default' => tk_default,
      'disconnect' => tk_disconnect,
      'do' => tk_do,
      'else' => tk_else,
      'enter' => tk_enter,
      'for' => tk_for,
      'function' => tk_function,
      'global' => tk_global,
      'if' => tk_if,
      'int' => tk_int,
      'lightning' => tk_lightning,
      'net' => tk_net,
      'open' => tk_open,
      'redreturn' => tk_red_return,
      'respawn' => tk_respawn,
      'restart' => tk_restart,
      'return' => tk_return,
      'script' => tk_script,
      'special' => tk_special,
      'static' => tk_static,
      'str' => tk_str,
      'suspend' => tk_suspend,
      'switch' => tk_switch,
      'terminate' => tk_terminate,
      'unloading' => tk_unloading,
      'until' => tk_until,
      'void' => tk_void,
      'while' => tk_while,
      'whitereturn' => tk_white_return,
      'world' => tk_world,
   );
   if ( isset( $reserved[ $id ] ) ) {
      $type = $reserved[ $id ];
   }
   else {
      $type = tk_id;
   }
   $text = $id;
   goto state_finish;

   state_zero:
   // --------------------------------------------------------------------
   if ( $front->ch == '.' ) {
      f_read_ch( $front );
      goto state_fixed;
   }
   else if ( $front->ch == 'x' || $front->ch == 'X' ) {
      f_read_ch( $front );
      while (
         ( $front->ch >= '0' && $front->ch <= '9' ) || 
         ( $front->ch >= 'a' && $front->ch <= 'f' ) || 
         ( $front->ch >= 'A' && $front->ch <= 'F' ) ) {
         f_read_ch( $front );
      }
      $type = tk_lit_hex;
      // Prefix not needed.
      $pos_s += 2;
   }
   else {
      $length = 0;
      while ( $front->ch >= '0' && $front->ch <= '7' ) {
         f_read_ch( $front );
         $length += 1;
      }
      if ( $length ) {
         $type = tk_lit_octal;
         // Prefix not needed.
         $pos_s += 1;
      }
      else {
         $type = tk_lit_decimal;
      }
   }
   goto state_finish;

   state_decimal:
   // --------------------------------------------------------------------
   while ( ctype_digit( $front->ch ) ) {
      f_read_ch( $front );
   }
   if ( $front->ch == '.' ) {
      f_read_ch( $front );
      goto state_fixed;
   }
   else {
      $type = tk_lit_decimal;
      goto state_finish;
   }

   state_fixed:
   // --------------------------------------------------------------------
   while ( ctype_digit( $front->ch ) ) {
      f_read_ch( $front );
   }
   $type = tk_lit_fixed;
   goto state_finish;

   state_assign:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_eq;
      goto state_finish;
   default:
      $type = tk_assign;
      goto state_finish;
   }

   state_exclamation:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_neq;
      goto state_finish;
   default:
      $type = tk_log_not;
      goto state_finish;
   }

   state_add:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '+':
      f_read_ch( $front );
      $type = tk_inc;
      goto state_finish;
   case '=':
      f_read_ch( $front );
      $type = tk_assign_add;
      goto state_finish;
   default:
      $type = tk_plus;
      goto state_finish;
   }

   state_sub:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '-':
      f_read_ch( $front );
      $type = tk_dec;
      goto state_finish;
   case '=':
      f_read_ch( $front );
      $type = tk_assign_sub;
      goto state_finish;
   default:
      $type = tk_minus;
      goto state_finish;
   }

   state_star:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_assign_mul;
      goto state_finish;
   default:
      $type = tk_star;
      goto state_finish;
   }

   state_slash:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_assign_div;
      goto state_finish;
   case '/':
      f_read_ch( $front );
      goto state_comment_line;
   case '*':
      f_read_ch( $front );
      goto state_comment_block;
   default:
      $type = tk_slash;
      goto state_finish;
   }

   state_percent:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_assign_mod;
      goto state_finish;
   default:
      $type = tk_mod;
      goto state_finish;
   }

   state_angleb_l:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_lte;
      goto state_finish;
   case '<':
      f_read_ch( $front );
      goto state_shift_l;
   default:
      $type = tk_lt;
      goto state_finish;
   }

   state_shift_l:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_assign_shift_l;
      goto state_finish;
   default:
      $type = tk_shift_l;
      goto state_finish;
   }

   state_angleb_r:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_gte;
      goto state_finish;
   case '>':
      f_read_ch( $front );
      goto state_shift_r;
   default:
      $type = tk_gt;
      goto state_finish;
   }

   state_shift_r:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_assign_shift_r;
      goto state_finish;
   default:
      $type = tk_shift_r;
      goto state_finish;
   }

   state_ampersand:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '&':
      f_read_ch( $front );
      $type = tk_log_and;
      goto state_finish;
   case '=':
      f_read_ch( $front );
      $type = tk_assign_bit_and;
      goto state_finish;
   default:
      $type = tk_bit_and;
      goto state_finish;
   }

   state_pipe:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '|':
      f_read_ch( $front );
      $type = tk_log_or;
      goto state_finish;
   case '=':
      f_read_ch( $front );
      $type = tk_assign_bit_or;
      goto state_finish;
   default:
      $type = tk_bit_or;
      goto state_finish;
   }

   state_caret:
   // --------------------------------------------------------------------
   switch ( $front->ch ) {
   case '=':
      f_read_ch( $front );
      $type = tk_assign_bit_xor;
      goto state_finish;
   default:
      $type = tk_bit_xor;
      goto state_finish;
   }

   state_comment_line:
   // --------------------------------------------------------------------
   while ( true ) {
      if ( $front->ch == '' || $front->ch == "\n" ) {
         goto state_start;
      }
      else {
         f_read_ch( $front );
      }
   }

   state_comment_block:
   // --------------------------------------------------------------------
   while ( true ) {
      if ( $front->ch == '' ) {
         f_diag( $front, array( 'msg' => 'unterminated comment',
            'type' => 'err', 'file' => $front->text_file,
            'line' => $front->text_line, 'column' => $front->text_column ) );
         f_bail( $front );
      }
      else if ( $front->ch == '*' && f_peek_ch( $front ) == '/' ) {
         f_read_ch( $front );
         f_read_ch( $front );
         goto state_start;
      }
      else {
         f_read_ch( $front );
      }
   }

   state_finish:
   // --------------------------------------------------------------------
   if ( ! $pos_e ) {
      $pos_e = $front->file->pos;
   }
   if ( $text == '' ) {
      $text = substr( $front->file->text, $pos_s, $pos_e - $pos_s );
   }
   return array( $type, array( 'file' => $front->file, 'line' => $line,
      'column' => $column ), $text );
}