mod.wizards.newContentElement.wizardItems.plugins {
	elements {
        pbsocial_socialfeed {
			iconIdentifier = pbsocial_socialfeed
			title = LLL:EXT:pb_social/Resources/Private/Language/de.locallang_db.xlf:socialfeed_wizard.title
			description = LLL:EXT:pb_social/Resources/Private/Language/de.locallang_db.xlf:socialfeed_wizard.description
			tt_content_defValues {
                CType = list
                list_type = pbsocial_socialfeed
			}
		}
	}
	show := addToList(pbsocial_socialfeed)
}