<?php
/**
 * @author SAPSAN 隼 #3604
 *
 * @link https://hlmod.ru/members/sapsan.83356/
 * @link https://github.com/sapsanDev
 *
 * @license GNU General Public License Version 3
 */


class ServerInfo
{
  CONST NB      = "\x00";

  private $IP;
  private $PORT;
  private $SOCKET;
  private $BUFER;

  function __construct( $_IP_PORT, $_TIMEOUT = 5 )
  {
    $_Data = explode(":", $_IP_PORT);

    if( empty($_Data[0]) && empty($_Data[1]) ) return false;

    $this->IP = gethostbyname($_Data[0]);
    $this->PORT = $_Data[1];

    $this->SOCKET = @fsockopen("udp://{$this->IP}", $this->PORT, $errno, $errstr, 1);

      if( empty($this->SOCKET) ) return false;

      stream_set_timeout( $this->SOCKET, $_TIMEOUT, 0);
      stream_set_blocking( $this->SOCKET, TRUE);
  }

  protected function SI_BYTE( $_LEN )
  {
    $_STR = substr( $this->BUFER, 0, $_LEN );
    $this->BUFER = substr( $this->BUFER, $_LEN );
    return $_STR;
  }

  protected function SI_STR( $_SB = 0, $_ME = ServerInfo::NB)
  {
    $this->BUFER = substr( $this->BUFER, $_SB );
    $_LEN = strpos( $this->BUFER, $_ME );

    if( $_LEN === false )
      $_LEN = strlen( $this->BUFER );

    $_STR = substr( $this->BUFER, 0, $_LEN );
    $this->BUFER = substr( $this->BUFER, $_LEN + strlen( $_ME ) );

     return $_STR;
  }

  protected function SI_UK( $_STR, $_FT )
  {
    list( , $_STR) = @unpack( $_FT, $_STR );

    return $_STR;
  }

  protected function SI_Get_Server_Info()
  {
    fwrite( $this->SOCKET, "\xFF\xFF\xFF\xFF\x54Source Engine Query" . ServerInfo::NB);

    $this->BUFER = fread( $this->SOCKET, 4096 ); 

    if( !$this->BUFER ) return false;

    $_Header = $this->SI_BYTE( 4 );

    $_Responce_Type  = $this->SI_BYTE( 1 );

    switch ( $_Responce_Type )
    {
      case "I":
        $_Server['Ip']          = $this->IP;
        $_Server['Netcode']     = ord( $this->SI_BYTE( 1 ) );
        $_Server['Name']        = $this->SI_STR();
        $_Server['Map']         = end(explode("/", $this->SI_STR()));
        $_Server['Game']        = $this->SI_STR();
        $_Server['Description'] = $this->SI_STR();
        $_Server['Appid']       = $this->SI_UK($this->SI_BYTE( 2 ), "S" );
        $_Server['Players']     = ord( $this->SI_BYTE( 1 ) );
        $_Server['Playersmax']  = ord( $this->SI_BYTE( 1 ) );
        $_Server['Bots']        = ord( $this->SI_BYTE( 1 ) );
        $_Server['Dedicated']   = $this->SI_BYTE( 1 );
        $_Server['Os']          = $this->SI_BYTE( 1 ) == "l" ? "Linux" : "Windows";
        $_Server['Anticheat']   = ord( $this->SI_BYTE( 1 ));
        $_Server['Version']     = $this->SI_STR();
      break;

      case "m":
        $_Server['Ip']            = $this->SI_STR();
	    $_Server['Name']          = $this->SI_STR();
	    $_Server['Map']           = end(explode("/", $this->SI_STR()));
	    $_Server['Game']          = $this->SI_STR();
	    $_Server['Description']   = $this->SI_STR();
	    $_Server['Players']       = ord( $this->SI_BYTE( 1 ) );
	    $_Server['Playersmax']    = ord( $this->SI_BYTE( 1 ) );
	    $_Server['Netcode']       = ord( $this->SI_BYTE( 1 ) );
	    $_Server['Dedicated']     = $this->SI_BYTE( 1 );
	    $_Server['Os']            = $this->SI_BYTE( 1 ) == "l" ? "Linux" : "Windows";

      if ( ord( $this->SI_BYTE( 1 ) ) )
      {
            $_Server['Mod_url_info']     = $this->SI_STR();
            $_Server['Mod_url_download'] = $this->SI_STR();

            $this->BUFER = substr( $this->BUFER, 1 );

            $_Server['Mod_version']      = $this->SI_UK( $this->SI_BYTE( 4 ), "l" );
            $_Server['Mod_size']         = $this->SI_UK( $this->SI_BYTE( 4 ), "l" );
            $_Server['Mod_server_side']  = ord( $this->SI_BYTE( 1 ) );
            $_Server['Mod_custom_dll']   = ord( $this->SI_BYTE( 1 ) );
          }

        $_Server['Anticheat'] = ord( $this->SI_BYTE( 1 ) );
        $_Server['Bots']      = ord( $this->SI_BYTE( 1 ) );
      break;
      
      default: return false; break;
    }

    $_Server["Port"] = $this->PORT;
    if( file_exists( '../../../../storage/cache/img/maps/' . $_Server['Appid'] . '/' . $_Server['Map'] . '.jpg') )
        $_Server['Map_image'] = 'storage/cache/img/maps/' . $_Server['Appid'] . '/' . $_Server['Map'] . '.jpg';
    else
        $_Server['Map_image'] = 'storage/cache/img/maps/' . $_Server['Appid'] . '/-.jpg';

    return (object) $_Server;
  }

  protected function SI_Get_Server_Players()
  {
    fwrite( $this->SOCKET, "\xFF\xFF\xFF\xFF\x55\x00\x00\x00\x00");

    $this->BUFER = fread( $this->SOCKET, 4096 );

    if(!$this->BUFER) return false;

    if(substr($this->BUFER, 0,  5) == "\xFF\xFF\xFF\xFF\x41")
    { 
      $_Chellenge = substr($this->BUFER, 5,  4);

      fwrite( $this->SOCKET, "\xFF\xFF\xFF\xFF\x55{$_Chellenge}");

      $this->BUFER = fread( $this->SOCKET, 4096 );

      if(!$this->BUFER) return false;

      $_Header = $this->SI_BYTE( 4 );

      $_Responce_Type = $this->SI_BYTE( 1 );

      if( $_Responce_Type == "D" )
      {

        $returned = ord ($this->SI_BYTE( 1 ) );
        $_Players = [];
        $player_key = 0;

        while ( $this->BUFER )
        {
          $_Players[$player_key]['Id']    = ord( $this->SI_BYTE( 1 ) );
          $_Players[$player_key]['Name']  = $this->SI_STR();
          $_Players[$player_key]['Score'] = $this->SI_UK( $this->SI_BYTE( 4 ), "l" );
          $_Players[$player_key]['Time']  = gmdate("H:i:s", $this->SI_UK( $this->SI_BYTE( 4 ), "f" ));
          $player_key ++;

          if(empty($_Players[$player_key]['Name']) && empty($_Players[$player_key]['Score']))
          {
          	unset($_Players[$player_key]);
          }
        }

        return $_Players;
      }
    }
  }

  /*
  * return server info 
  *	Get_Info return object 
  * Get_Players return array [Id, Name, Score, Time (Unixtime)]
  */
  public function SI_Get()
  {
  	return (object) $_Server_Info = [ "info" => $this->SI_Get_Server_Info(), "players" => $this->SI_Get_Server_Players() ];
  }
}