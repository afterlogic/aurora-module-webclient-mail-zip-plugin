'use strict';

module.exports = function (oAppData) {
	var App = require('%PathToCoreWebclientModule%/js/App.js');
	
	if (App.getUserRole() === Enums.UserRole.NormalUser)
	{
		var
			_ = require('underscore'),
			ko = require('knockout'),
			
			TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
			Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
			
			Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
			
			bAllowZip = oAppData['%ModuleName%'] ? !!oAppData['%ModuleName%'].AllowZip : false
		;
		
		
		return {
			start: function (ModulesManager) {
				if (bAllowZip)
				{
					App.subscribeEvent('MailWebclient::ParseFile::after', function (oFile) {
						if (oFile && _.isFunction(oFile.addAction) && oFile.extension() === 'zip')
						{
							oFile.mailzipSubFilesLoaded = ko.observable(false);
							oFile.mailzipSubFilesLoading = ko.observable(false);
							oFile.mailzipExpandFile = function ()
							{
								if (!this.mailzipSubFilesLoaded() && !this.mailzipSubFilesLoading())
								{
									this.mailzipSubFilesLoading(true);
									Ajax.send('%ModuleName%', 'ExpandFile', { 'Hash': this.hash() }, function (oResponse) {
										this.mailzipSubFilesLoading(false);
										if (oResponse.Result)
										{
											this.subFiles([]);
											if (Types.isNonEmptyArray(oResponse.Result))
											{
												_.each(oResponse.Result, _.bind(function (oRawFile) {
													var oSubFile = oFile.getNewInstance();
													oSubFile.parse(oRawFile);
													this.subFiles.push(oSubFile);
												}, this));
											}
											this.mailzipSubFilesLoaded(true);
											this.subFilesExpanded(true);
										}
									}, this);
								}
								else
								{
									this.subFilesExpanded(true);
								}
							};
							
							var oActionData = {
								'Text': ko.computed(function () {
									if (this.subFilesExpanded())
									{
										return TextUtils.i18n('COREWEBCLIENT/ACTION_COLLAPSE_FILE');
									}
									if (this.mailzipSubFilesLoading())
									{
										return TextUtils.i18n('COREWEBCLIENT/INFO_LOADING');
									}
									return TextUtils.i18n('COREWEBCLIENT/ACTION_EXPAND_FILE');
								}, oFile),
								'Handler': _.bind(function () {
									if (!this.mailzipSubFilesLoading())
									{
										if (this.subFilesExpanded())
										{
											this.subFilesExpanded(false);
										}
										else
										{
											this.mailzipExpandFile();
										}
									}
								}, oFile)
							};
							
							oFile.addAction('expand', true, oActionData);
							oFile.removeAction('view');
						}
					});
				}
			}
		};
	}
	
	return null;
};
