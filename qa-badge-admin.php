<?php
	class qa_badge_admin {
		
		function allow_template($template)
		{
			return ($template!='admin');
		}

		function admin_form(&$qa_content)
		{

		//	Process form input

			$ok = null;

			$badges = qa_get_badge_list();
			if (qa_clicked('badge_rebuild_button')) {
				qa_import_badge_list();
				$ok = qa_badge_lang('badges/list_rebuilt');
			}
			else if (qa_clicked('badge_award_button')) {
				if((bool)qa_post_text('badge_award_delete')) {
					qa_db_query_sub(
						'DROP TABLE ^userbadges'
					);
					qa_db_query_sub(
						'CREATE TABLE ^userbadges ('.
							'awarded_at DATETIME NOT NULL,'.
							'user_id INT(11) NOT NULL,'.
							'notify TINYINT DEFAULT 0 NOT NULL,'.
							'object_id INT(10),'.
							'badge_slug VARCHAR (64) CHARACTER SET ascii DEFAULT \'\','.
							'id INT(11) NOT NULL AUTO_INCREMENT,'.
							'PRIMARY KEY (id)'.
						') ENGINE=MyISAM DEFAULT CHARSET=utf8'
					);
				}
				$ok = $this->qa_check_all_users_badges();
			}
			else if (qa_clicked('badge_reset_button')) {
				foreach ($badges as $slug => $info) {
					if(isset($info['var'])) {
						qa_opt('badge_'.$slug.'_var',$info['var']);
					}
					qa_opt('badge_'.$slug.'_name',qa_badge_lang('badges/'.$slug));
					qa_opt('badge_'.$slug.'_enabled','1');
				}
				$ok = qa_badge_lang('badges/badges_reset');
			}
			else if (qa_clicked('badge_trigger_notify')) {
				$this->trigger_notify('Congratulations!  This is a test message');
			}
			else if(qa_clicked('badge_save_settings')) {
				foreach ($badges as $slug => $info) {
					
					// update var
					
					if(isset($info['var']) && qa_post_text('badge_'.$slug.'_var')) {
						qa_opt('badge_'.$slug.'_var',qa_post_text('badge_'.$slug.'_var'));
					}

					// toggle activation

					if((bool)qa_post_text('badge_'.$slug.'_enabled') === false) {
						qa_opt('badge_'.$slug.'_enabled','0');
					}
					else qa_opt('badge_'.$slug.'_enabled','1');

					// set custom names
					
					if (qa_post_text('badge_'.$slug.'_edit') != qa_opt('badge_'.$slug.'_name')) {
						qa_opt('badge_'.$slug.'_name',qa_post_text('badge_'.$slug.'_edit'));
						$qa_badge_lang_default['badges'][$slug] = qa_opt('badge_'.$slug.'_name');
					}
					
				}
				
				// options
				
				qa_opt('badge_notify_time', (int)qa_post_text('badge_notify_time'));			
				qa_opt('badge_admin_user_widget',(bool)qa_post_text('badge_admin_user_widget'));
				qa_opt('badge_admin_user_field',(bool)qa_post_text('badge_admin_user_field'));
				qa_opt('badge_active', (bool)qa_post_text('badge_active_check'));			
				$ok = qa_badge_lang('badges/badge_admin_saved');
			}
			
		//	Create the form for display
			
			
			$fields = array();
			
			$fields[] = array(
				'label' => qa_badge_lang('badges/badge_admin_activate'),
				'tags' => 'NAME="badge_active_check"',
				'value' => qa_opt('badge_active'),
				'type' => 'checkbox',
			);

			if(qa_opt('badge_active')) {

				$fields[] = array(
						'label' => qa_badge_lang('badges/active_badges').':',
						'type' => 'static',
				);


				foreach ($badges as $slug => $info) {
					$badge_name=qa_badge_lang('badges/'.$slug);
					$badge_desc=qa_badge_lang('badges/'.$slug.'_desc');
					if(!qa_opt('badge_'.$slug.'_name')) qa_opt('badge_'.$slug.'_name',$badge_name);
					$name = qa_opt('badge_'.$slug.'_name');
					
					$type = qa_get_badge_type($info['type']);
					$types = $type['slug'];
					
					if(isset($info['var'])) {
						$htmlout = str_replace('#','<input type="text" name="badge_'.$slug.'_var" size="4" value="'.qa_opt('badge_'.$slug.'_var').'">',$badge_desc);
						$fields[] = array(
								'type' => 'static',
								'note' => '<table><tr><td><input type="checkbox" name="badge_'.$slug.'_enabled"'.(qa_opt('badge_'.$slug.'_enabled') !== '0' ? ' checked':'').'></td><td><input type="text" name="badge_'.$slug.'_edit" id="badge_'.$slug.'_edit" style="display:none" size="16" onblur="badgeEdit(\''.$slug.'\',true)" value="'.$name.'"><span id="badge_'.$slug.'_badge" class="badge-'.$types.'" onclick="badgeEdit(\''.$slug.'\')">'.$name.'</span></td><td>'.$htmlout.'</td></tr></table>'
						);
					}
					else {
						$fields[] = array(
								'type' => 'static',
								'note' => '<table><tr><td><input type="checkbox" name="badge_'.$slug.'_enabled"'.(qa_opt('badge_'.$slug.'_enabled') !== '0' ? ' checked':'').'></td><td><input type="text" name="badge_'.$slug.'_edit" id="badge_'.$slug.'_edit" style="display:none" size="16" onblur="badgeEdit(\''.$slug.'\',true)" value="'.$name.'"><span id="badge_'.$slug.'_badge" class="badge-'.$types.'" onclick="badgeEdit(\''.$slug.'\')">'.$name.'</span></td><td>'.$badge_desc.'</td></tr></table>'
						);
					}
				}
				$fields[] = array(
						'label' => '<hr/>'.qa_badge_lang('badges/notify_time').':',
						'type' => 'number',
						'value' => qa_opt('badge_notify_time'),
						'tags' => 'NAME="badge_notify_time"',
						'note' => '<em>'.qa_badge_lang('badges/notify_time_desc').'</em><hr/>',
				);
				$fields[] = array(
					'label' => qa_badge_lang('badges/badge_admin_user_field'),
					'tags' => 'NAME="badge_admin_user_field"',
					'value' => (bool)qa_opt('badge_admin_user_field'),
					'type' => 'checkbox',
				);				
				$fields[] = array(
					'label' => qa_badge_lang('badges/badge_admin_user_widget'),
					'tags' => 'NAME="badge_admin_user_widget"',
					'value' => (bool)qa_opt('badge_admin_user_widget'),
					'type' => 'checkbox',
				);				
			}
			
			$notifyjs = "jQuery('body').prepend('<div class='notify-container'><div class='badge-notify notify'>This is a test notification!<div class='notify-close' onclick='$(this).parent().fadeOut()'>x</div></div></div>')".(qa_opt('badge_notify_time') != '0'?"jQuery('document').ready(function() { $('.notify-container').delay(".((int)qa_opt('badge_notify_time')*1000).").fadeOut(); });":"");
			
			return array(
				'ok' => ($ok && !isset($error)) ? $ok : null,
				
				'fields' => $fields,
				
				'buttons' => array(
					array(
						'label' => qa_badge_lang('badges/badge_recreate'),
						'tags' => 'NAME="badge_rebuild_button"',
						'note' => '<br/><em>'.qa_badge_lang('badges/badge_recreate_desc').'</em><hr/>',
					),
					array(
						'label' => qa_badge_lang('badges/badge_award_button'),
						'tags' => 'NAME="badge_award_button"',
						'note' => '<br/><em>'.qa_badge_lang('badges/badge_award_button_desc').'</em><br/><input type="checkbox" name="badge_award_delete"><b>'.qa_badge_lang('badges/badge_award_delete_desc').'</b><hr/>',
					),
					array(
						'label' => qa_badge_lang('badges/reset_values'),
						'tags' => 'NAME="badge_reset_button"',
						'note' => '<br/><em>'.qa_badge_lang('badges/reset_values_desc').'</em><hr/>',
					),
					array(
						'label' => qa_badge_lang('badges/badge_trigger_notify'),
						'tags' => 'name="badge_trigger_notify" onclick="'.$notifyjs.'"',
						'note' => '<br/><em>'.qa_badge_lang('badges/badge_trigger_notify_desc').'</em><hr/>',
					),
					array(
						'label' => qa_badge_lang('badges/save_settings'),
						'tags' => 'NAME="badge_save_settings"',
						'note' => '<br/><em>'.qa_badge_lang('badges/save_settings_desc').'</em>',
					),
				),
			);
		}
		
// imported user badge checking functions

		function award_badge($object_id, $user_id, $badge_slug) {
			
			// add badge to userbadges
			
			qa_db_query_sub(
				'INSERT INTO ^userbadges (awarded_at, notify, object_id, user_id, badge_slug, id) '.
				'VALUES (NOW(), #, #, #, #, 0)',
				0, $object_id, $user_id, $badge_slug
			);
			
		}
		function get_post_data($id) {
			$result = qa_db_read_one_assoc(
				qa_db_query_sub(
					'SELECT * FROM ^posts WHERE postid=#',
					$id
				),
				true
			);
			return $result;
		}
		
	// post check

		function qa_check_all_users_badges() {

			$awarded = 0;
			$users;
		
			$post_result = qa_db_read_all_assoc(
				qa_db_query_sub(
					'SELECT * FROM ^posts'
				)
			);
			
			foreach ($post_result as $post) {
				$user='user'.$post['userid'];
				$pid = $post['postid'];
				$pt = $post['type'];
				
				// get post count
				
				if(isset($users[$user]) && isset($users[$user][$pt])) $users[$user][$pt]++;
				else $users[$user][$pt] = 1;
				
				// get post votes
				
				if($post['netvotes'] !=0) $users[$user][$pt.'votes'][] = array(
					'id'=>$pid,
					'votes'=>$post['netvotes'],
					'parentid'=>$post['parentid'],
					'created'=>$post['created']
				);

				// get post views
				
				if($post['views']) $users[$user]['views'][] = array(
					'id'=>$pid,
					'views'=>$post['views']
				);
				 
			} 


			$vote_result = qa_db_read_all_assoc(
				qa_db_query_sub(
					'SELECT * FROM ^uservotes WHERE vote <> 0'
				)
			);
			foreach ($vote_result as $vote) {
				$user='user'.$vote['userid'];
				$pid = $vote['postid'];
				
				// get vote count
				
				if(isset($users[$user]) && isset($users[$user]['votes'])) $users[$user]['votes'] = $users[$user]['votes']++;
				else $users[$user]['votes'] = 1;
			} 

			// flags

			$flag_result = qa_db_read_all_values(
				qa_db_query_sub(
					'SELECT userid FROM ^uservotes WHERE flag > 0'
				),
				true
			);

			foreach ($flag_result as $flag) {
				$user='user'.$flag['userid'];
				
				// get flag count
				
				if(isset($users[$user]) && isset($users[$user]['flags'])) $users[$user]['flags'] = $users[$user]['flags']++;
				else $users[$user]['flags'] = 1;
			}
			
			foreach ($users as $user => $data) {
				$uid = (int)substr($user,4);
				
				// bulk posts
				
				$badges = array(
					'Q' => array('asker','questioner','inquisitor'),
					'A' => array('answerer','lecturer','preacher'),
					'C' => array('commenter','commentator','annotator')
				);
			
				foreach($badges as $pt => $slugs) {
					foreach($slugs as $badge_slug) {
						if(!isset($data[$pt])) continue;
						if($data[$pt] >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
							
							$result = qa_db_read_one_value(
								qa_db_query_sub(
									'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
									$uid, $badge_slug
								),
								true
							);
							
							if (!$result) { // not already awarded this badge
								$this->award_badge($pid, $uid, $badge_slug,false,true);
								$awarded++;
							}
						}
					}
				}

				// nice Q&A
				
				$badges = array(
					'Q' => array('nice_question','good_question','great_question'), 
					'A' => array('nice_answer','good_answer','great_answer')
				);
			
				foreach($badges as $pt => $slugs) {
					foreach($slugs as $badge_slug) {
						if(!isset($data[$pt.'votes'])) continue;
						foreach($data[$pt.'votes'] as $idv) {
							
							if($idv['votes'] >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
								
								$result = qa_db_read_one_value(
									qa_db_query_sub(
										'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND object_id=# AND badge_slug=$',
										$uid, $idv['id'], $badge_slug
									),
									true
								);
								
								if (!$result) { // not already awarded this badge
									$this->award_badge($idv['id'], $uid, $badge_slug,false,true);
									$awarded++;
								}

							// old question answer vote checks
								if($pt == 'A') {
									$qid = $idv['parentid'];
									$create = strtotime($post['created']);
									
									$parent = $this->get_post_data($qid);
									$pcreate = strtotime($parent['created']);
									
									$diffd = round(abs($pcreate-$create)/60/60/24);
									

									$badge_slug2 = $badge_slug.'_old';
									
									if($diff  >= (int)qa_opt('badge_'.$badge_slug2.'_var') && qa_opt('badge_'.$badge_slug2.'_enabled') !== '0') {
										$result = qa_db_read_one_value(
											qa_db_query_sub(
												'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND object_id=# AND badge_slug=$',
												$uid, $idv['id'], $badge_slug2
											),
											true
										);
										if (!$result) { // not already awarded for this answer
											$this->award_badge($idv['id'], $uid, $badge_slug2);
											$awarded++;
										}
									}
								}
							}
						}
					}
				}

				// votes per user badges

				$votes = $data['votes']; 
				
				$badges = array('voter','avid_voter','devoted_voter');

				foreach($badges as $badge_slug) {
					if($votes  >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
						$result = qa_db_read_one_value(
							qa_db_query_sub(
								'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
								$uid, $badge_slug
							),
							true
						);
						
						if (!$result) { // not already awarded this badge
							$this->award_badge(NULL, $uid, $badge_slug,false,true);
							$awarded++;
						}
					}
				}
				
				// views per post badges
				
				$badges = array('notable_question','popular_question','famous_question');
				
				foreach($badges as $badge_slug) {
					if(!isset($data['views'])) continue;
					foreach($data['views'] as $idv) {
						if($idv['views'] >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
							
							$result = qa_db_read_one_value(
								qa_db_query_sub(
									'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND object_id=# AND badge_slug=$',
									$uid, $idv['id'], $badge_slug
								),
								true
							);
							
							if (!$result) { // not already awarded this badge
								$this->award_badge($idv['id'], $uid, $badge_slug,false,true);
								$awarded++;
							}
						}
					}
				}
				
				// flags per user

				$flags = $data['flags']; 
				
				$badges = array('watchdog','bloodhound','pitbull');

				foreach($badges as $badge_slug) {
					if((int)$flags  >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
						$result = qa_db_read_one_value(
							qa_db_query_sub(
								'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
								$uid, $badge_slug
							),
							true
						);
						
						if (!$result) { // not already awarded this badge
							$this->award_badge(NULL, $uid, $badge_slug,false,true);
							$awarded++;
						}
					}
				}
			}



		// selects, selecteds
			
			$selects = qa_db_read_all_assoc(
				qa_db_query_sub(
					'SELECT aselects, aselecteds userid FROM ^userpoints'
				)
			);
			
			foreach($selects as $s) {
				$uid = $s['userid'];

				$badges = array('gifted','wise','enlightened');
				$count = $s['aselects'];

				foreach($badges as $badge_slug) {
					if((int)$count  >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
						$result = qa_db_read_one_value(
							qa_db_query_sub(
								'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
								$uid, $badge_slug
							),
							true
						);
						
						if (!$result) { // not already awarded this badge
							$this->award_badge(NULL, $uid, $badge_slug,false,true);
							$awarded++;
						}
					}
				}

				$badges = array('grateful','respectful','reverential');
				$count = $s['aselecteds'];

				foreach($badges as $badge_slug) {
					if((int)$count  >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
						$result = qa_db_read_one_value(
							qa_db_query_sub(
								'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
								$uid, $badge_slug
							),
							true
						);
						
						if (!$result) { // not already awarded this badge
							$this->award_badge(NULL, $uid, $badge_slug,false,true);
							$awarded++;
						}
					}			
				}
			}
		// edits
		
			$counts = qa_db_read_all_assoc(
				qa_db_query_sub(
					'SELECT posts_edited,user_id FROM ^achievements WHERE posts_edited > 0'
				)
			);
			
			foreach($counts as $c) {
				$count = $c['posts_edited'];
				$uid = $c['user_id'];
				$badges = array('editor','copy_editor','senior_editor');

				foreach($badges as $badge_slug) {
					if((int)$count  >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
						$result = qa_db_read_one_value(
							qa_db_query_sub(
								'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
								$uid, $badge_slug
							),
							true
						);
						
						if (!$result) { // not already awarded this badge
							$this->award_badge(NULL, $uid, $badge_slug,false,true);
							$awarded++;
						}
					}
				}
			}
 

			foreach($users as $user => $data) {
				$uid = (int)substr($user,4);
				
				$flags = $data['flags']; 
				
				$badges = array('watchdog','bloodhound','pitbull');

				foreach($badges as $badge_slug) {
					if((int)$flags  >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
						$result = qa_db_read_one_value(
							qa_db_query_sub(
								'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
								$uid, $badge_slug
							),
							true
						);
						
						if (!$result) { // not already awarded this badge
							$this->award_badge(NULL, $uid, $badge_slug,false,true);
							$awarded++;
						}
					}
				}
			}
		// check badges
	
			$counts = qa_db_read_all_values(
				qa_db_query_sub(
					'SELECT user_id FROM ^userbadges'
				),
				true
			);
			foreach ($counts as $medal) {
				$uid='user'.$medal['userid'];
				
				// get flag count
				
				if(isset($userposts[$uid]) && isset($userposts[$uid]['medals'])) $userposts[$uid]['medals'] = $userposts[$uid]['medals']++;
				else $userposts[$uid]['medals'] = 1;
			} 

			foreach($userposts as $user => $data) {
				$uid = (int)substr($user,4);
				
				$medals = $data['medals']; 
				
				$badges = array('medalist','champion','olympian');

				foreach($badges as $badge_slug) {
					if((int)$medals  >= (int)qa_opt('badge_'.$badge_slug.'_var') && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
						$result = qa_db_read_one_value(
							qa_db_query_sub(
								'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
								$uid, $badge_slug
							),
							true
						);
						
						if (!$result) { // not already awarded this badge
							$this->award_badge(NULL, $uid, $badge_slug,false,true);
							$awarded++;
						}
					}
				}
			}
			return $awarded.' badge'.($awarded != 1 ? 's':'').' awarded.';
		}
	}
