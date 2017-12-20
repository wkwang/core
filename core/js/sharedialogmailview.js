/*
 * Copyright (c) 2016
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	if (!OC.Share) {
		OC.Share = {};
	}

	var TEMPLATE =
		'<form id="emailPrivateLink" class="emailPrivateLinkForm">' +
		'    <label class="public-link-modal--label" for="emailPrivateLinkField-{{cid}}">{{mailLabel}}</label>' +
		'    <input class="public-link-modal--input emailField" id="emailPrivateLinkField-{{cid}}" value="{{email}}" placeholder="{{mailPlaceholder}}" type="email" />' +
		'    <label class="public-link-modal--label" for="emailBodyPrivateLinkField-{{cid}}">{{mailMessageLabel}}</label>' +
		'    <textarea class="public-link-modal--input emailBodyField" id="emailBodyPrivateLinkField-{{cid}}" rows="3" placeholder="{{mailBodyPlaceholder}}" style="display:none"></textarea>' +
		'</form>';

	/**
	 * @class OCA.Share.ShareDialogMailView
	 * @member {OC.Share.ShareItemModel} model
	 * @member {jQuery} $el
	 * @memberof OCA.Sharing
	 * @classdesc
	 *
	 * Represents the GUI of the share dialogue
	 *
	 */
	var ShareDialogMailView = OC.Backbone.View.extend({
		/** @type {string} **/
		id: 'shareDialogMailView',

		events: {
			"keyup .emailField": "toggleMailBody",
			"keydown .emailBodyField": "expandMailBody"
		},

		/** @type {Function} **/
		_template: undefined,

		initialize: function(options) {
			if (!_.isUndefined(options.itemModel)) {
				this.itemModel = options.itemModel;
			} else {
				throw 'missing OC.Share.ShareItemModel';
			}
		},

		toggleMailBody: function() {
			var $email = this.$el.find('.emailField');
			var $emailBody = this.$el.find('.emailBodyField');

			if ($email.val().length > 0 && $emailBody.is(":hidden")) {
				$emailBody.slideDown();
			} else if ($email.val().length === 0 && $emailBody.is(":visible")) {
				$emailBody.slideUp();
			}
		},

		expandMailBody: function(event) {
			var $emailBody = this.$el.find('.emailBodyField');
			$emailBody.css('minHeight', $emailBody[0].scrollHeight - 12);

			if (event.keyCode == 13) {
				event.stopPropagation();
			}
		},

		/**
		 * Send the link share information by email
		 *
		 * @param {string} recipientEmail recipient email address
		 */
		_sendEmailPrivateLink: function(recipientEmail, emailBody) {
			var deferred = $.Deferred();
			var itemType = this.itemModel.get('itemType');
			var itemSource = this.itemModel.get('itemSource');

			if (!this.validateEmail(recipientEmail)) {
				return deferred.reject({
					message: t('core', '{email} is not a valid address!', {email: recipientEmail})
				});
			}

			$.post(
				OC.generateUrl('core/ajax/share.php'), {
					action: 'email',
					toaddress: recipientEmail,
					emailBody: emailBody,
					link: this.model.getLink(),
					itemType: itemType,
					itemSource: itemSource,
					file: this.itemModel.getFileInfo().get('name'),
					expiration: this.model.get('expireDate') || ''
				},
				function(result) {
					if (!result || result.status !== 'success') {
						deferred.reject({
							message: result.data.message
						});
					} else {
						deferred.resolve();
					}
			}).fail(function(error) {
				return deferred.reject();
			});

			return deferred.promise();
		},

		validateEmail: function(email) {
			return email.match(/([\w\.\-_]+)?\w+@[\w-_]+(\.\w+){1,}$/);
		},

		sendEmails: function() {
			var $emailField = this.$el.find('.emailField');
			var $emailBodyField = this.$el.find('.emailBodyField');
			var $emailButton = this.$el.find('.emailButton');
			var email = $emailField.val();
			var emailBody = $emailBodyField.val().trim();

			if (email !== '') {
				$emailButton.prop('disabled', true);
				$emailField.val(t('core', 'Sending ...'));
				return this._sendEmailPrivateLink(email, emailBody).done(function() {
					$emailField.css('font-weight', 'bold').val(t('core', 'Email sent'));
					setTimeout(function() {
						$emailField.val('');
						$emailField.css('font-weight', 'normal');
						$emailField.prop('disabled', false);
						$emailButton.prop('disabled', false);
					}, 2000);
				}).fail(function() {
					$emailField.val(email);
					$emailField.css('font-weight', 'normal');
					$emailField.prop('disabled', false);
					$emailButton.prop('disabled', false);
					$emailField
						.prop('disabled', false)
						.val('');

				}).fail(function(error) {
					OC.dialogs.info(error.message, t('core', 'An error occured'));
					$emailButton.prop('disabled', false);
					$emailField
						.css('color', 'red')
						.prop('disabled', false)
						.val(email);
				});
			}
			return $.Deferred().resolve();
		},

		render: function() {
			var $email = this.$el.find('.emailField');
			var email = $email.val();

			this.$el.html(this.template({
				cid: this.cid,
				mailPlaceholder: t('core', 'Email link to person'),
				mailLabel: t('core', 'Send link via email'),
				mailBodyPlaceholder: t('core', 'Add personal message'),
				email: email
			}));

			if ($email.length !== 0) {
				$email.autocomplete({
						minLength: 1,
						source: function(search, response) {
							$.get(
								OC.generateUrl('core/ajax/share.php'), {
									fetch: 'getShareWithEmail',
									search: search.term
								},
								function(result) {
									if (result.status == 'success' && result.data.length > 0) {
										response(result.data);
									}
								});
						},
						select: function(event, item) {
							$email.val(item.item.email);
							return false;
						}
					})
					.data("ui-autocomplete")._renderItem = function(ul, item) {
						return $('<li>')
							.append('<a>' + escapeHTML(item.displayname) + "<br>" + escapeHTML(item.email) + '</a>')
							.appendTo(ul);
					};
			}
			this.delegateEvents();

			return this;
		},

		/**
		 * @returns {Function} from Handlebars
		 * @private
		 */
		template: function(data) {
			if (!this._template) {
				this._template = Handlebars.compile(TEMPLATE);
			}
			return this._template(data);
		}

	});

	OC.Share.ShareDialogMailView = ShareDialogMailView;

})();
