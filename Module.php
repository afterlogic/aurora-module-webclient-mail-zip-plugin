<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailZipWebclientPlugin;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	/* 
	 * @var $oApiFileCache \Aurora\System\Managers\Filecache\Manager 
	 */	
	public $oApiFileCache = null;
	
	public function init() 
	{
		$this->oApiFileCache = \Aurora\System\Api::GetSystemManager('Filecache');
	}
	
	/**
	 * Obtains list of module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		return array(
			'AllowZip' => class_exists('ZipArchive')
		);
	}

	public function ExpandFile($UserId, $Hash)
	{
		$mResult = array();
		
		$sUUID = \Aurora\System\Api::getUserUUIDById($UserId);
		$aValues = \Aurora\System\Api::DecodeKeyValues($Hash);
		$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Mail');
		$aFiles = $oCoreDecorator->SaveAttachmentsAsTempFiles($aValues['AccountID'], [$Hash]);
		foreach ($aFiles as $sTempName => $sHash)
		{
			if ($sHash === $Hash)
			{
				$sTempZipPath = $this->oApiFileCache->generateFullFilePath($sUUID, $sTempName);
				$mResult = $this->expandZipAttachment($sUUID, $sTempZipPath);
			}
		}
			
		return $mResult;
	}
	
	private function expandZipAttachment($sUUID, $sTempZipPath)
	{
		$mResult = array();
		
		$oZip = new \ZipArchive();
		if ($oZip->open($sTempZipPath))
		{
			for ($iIndex = 0; $iIndex < $oZip->numFiles; $iIndex++)
			{
				$aStat = $oZip->statIndex($iIndex);
				$sFile = $oZip->getFromIndex($iIndex);
				$iFileSize = $sFile ? strlen($sFile) : 0;

				if ($aStat && $sFile && 0 < $iFileSize && !empty($aStat['name']))
				{
					$sFileName = \MailSo\Base\Utils::Utf8Clear(basename($aStat['name']));
					$sTempName = md5(microtime(true).rand(1000, 9999));

					if ($this->oApiFileCache->put($sUUID, $sTempName, $sFile))
					{
						unset($sFile);

						$mResult[] = \Aurora\System\Utils::GetClientFileResponse(\Aurora\System\Api::getAuthenticatedUserId(), $sFileName, $sTempName, $iFileSize);
					}
					else
					{
						unset($sFile);
					}
				}
			}

			$oZip->close();
		}

		return $mResult;
	}
	
	/**
	 * @param int $UserId
	 * @param int $AccountID
	 * @param array $Attachments
	 * @return boolean
	 */
	public function SaveAttachments($UserId, $AccountID, $Attachments = array())
	{
		$mResult = false;
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$aAddFiles = array();
		
		$oMailModuleDecorator = \Aurora\System\Api::GetModuleDecorator('Mail');
		if ($oMailModuleDecorator)
		{
			$aTempFiles = $oMailModuleDecorator->SaveAttachmentsAsTempFiles($AccountID, $Attachments);
			if (\is_array($aTempFiles))
			{
				$sUUID = \Aurora\System\Api::getUserUUIDById($UserId);
				foreach ($aTempFiles as $sTempName => $sData)
				{
					$aData = \Aurora\System\Api::DecodeKeyValues($sData);
					if (\is_array($aData) && isset($aData['FileName']))
					{
						$sFileName = (string) $aData['FileName'];
						$sTempPath = $this->oApiFileCache->generateFullFilePath($sUUID, $sTempName);
						$aAddFiles[] = array($sTempPath, $sFileName);
					}
				}
			}			
		}
		
		if (count($aAddFiles) > 0)
		{
			$oZip = new \ZipArchive();
			
			$sZipTempName = md5(microtime());
			$sZipTempPath = $this->oApiFileCache->generateFullFilePath($sUUID, $sZipTempName);
			if ($oZip->open($sZipTempPath, \ZipArchive::CREATE))
			{
				foreach ($aAddFiles as $aItem)
				{
					$oZip->addFile($aItem[0], $aItem[1]);
				}
				$oZip->close();
				$iFileSize =  $this->oApiFileCache->fileSize($sUUID, $sZipTempName);
				$mResult = \Aurora\System\Utils::GetClientFileResponse($UserId, 'attachments.zip', $sZipTempName, $iFileSize);
			}
		}
		
		return $mResult;
	}
}
