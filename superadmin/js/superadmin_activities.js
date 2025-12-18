// File: js/admin_activities.js
document.addEventListener('DOMContentLoaded', function() {

    /* ------------------------------------------------------------------
       1. Tab Switching Logic
    ------------------------------------------------------------------ */
    const tabs = document.querySelectorAll('.tab-item');
    const tabContents = document.querySelectorAll('.tab-content');
    const bodyElement = document.body;
    const initialTab = bodyElement.getAttribute('data-initial-tab') || 'pending';

    function activateTab(tabName) {
        tabs.forEach(t => t.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));

        const activeTabButton = document.querySelector(`.tab-item[data-tab="${tabName}"]`);
        if (activeTabButton) activeTabButton.classList.add('active');

        const activeContent = document.getElementById(tabName + '-tab');
        if (activeContent) activeContent.classList.add('active');
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            activateTab(tabName);
            history.pushState(null, null, `activities.php?active_tab=${tabName}`);
        });
    });
    
    activateTab(initialTab);

    /* ------------------------------------------------------------------
       2. Activity Preview Modal Logic
    ------------------------------------------------------------------ */
    const previewModal = document.getElementById('previewActivityModal');
    const previewTitle = document.getElementById('previewActivityName');
    const previewMentor = document.getElementById('previewMentorName');
    const previewCourse = document.getElementById('previewCourseTitle');
    const previewLesson = document.getElementById('previewLessonName');
    const previewStatus = document.getElementById('previewStatusTag');
    const previewFileLink = document.getElementById('previewFileLink');
    const noPreviewFileMsg = document.getElementById('noPreviewFileMsg');
    const previewQuestionsContainer = document.getElementById('previewQuestionsContainer');

    function renderQuestionPreview(questionData, index) {
        const isMC = questionData.type === 'Multiple Choice';
        let html = `
            <div class="question-card" data-index="${index}">
                <div class="card-header">
                    <h4>Question #${index + 1} (${questionData.type})</h4>
                </div>
                <div class="form-group">
                    <p class="preview-question-text">${questionData.question}</p>
                </div>
        `;
        
        if (isMC && Array.isArray(questionData.choices) && questionData.choices.length > 0) {
            html += '<div class="preview-options-container">';
            questionData.choices.forEach((choice, i) => {
                const choiceNumber = i + 1;
                const isCorrect = questionData.correct_answer === `Choice ${choiceNumber}`;
                html += `
                    <div class="option ${isCorrect ? 'correct' : ''}">
                        ${String.fromCharCode(65 + i)}. ${choice} 
                        ${isCorrect ? '<i class="bx bx-check-circle" style="color: var(--success-color); margin-left: 5px;"></i> (Correct Answer)' : ''}
                    </div>
                `;
            });
            html += '</div>';
        } else if (questionData.type === 'Identification') {
            const answers = Array.isArray(questionData.acceptable_answers) ? questionData.acceptable_answers.join(', ') : questionData.acceptable_answers;
            html += `
                <div class="identification-answer-preview">
                    <p><strong>Acceptable Answers:</strong> 
                        <span style="color: var(--secondary-color); font-weight: 600;">${answers || 'No answer set'}</span>
                    </p>
                </div>
            `;
        }
        
        html += '</div>';
        return html;
    }

    // Attach listeners to all preview buttons
    document.querySelectorAll('.preview-activity-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            document.getElementById('previewModalTitle').textContent = "Activity Preview: " + data.title;
            previewTitle.textContent = data.title;
            previewMentor.textContent = data.mentor;
            previewCourse.textContent = data.course;
            previewLesson.textContent = data.lesson;
            
            // Status Tag
            previewStatus.textContent = data.status;
            previewStatus.className = 'status-tag ' + data.status.toLowerCase().replace(' ', '-');

            // File Link
            if (data.file && data.file.trim() !== '' && data.file !== 'null') {
                const filePath = encodeURIComponent(data.file);
                const viewURL = `view_file.php?file=${filePath}`;
                
                previewFileLink.href = viewURL;
                previewFileLink.setAttribute('target', '_blank');
                previewFileLink.style.display = 'inline-flex';
                noPreviewFileMsg.style.display = 'none';
            } else {
                previewFileLink.style.display = 'none';
                noPreviewFileMsg.style.display = 'block';
            }

            // Questions
            try {
                const questions = JSON.parse(data.questions);
                previewQuestionsContainer.innerHTML = questions.map(renderQuestionPreview).join('');
            } catch (e) {
                console.error("Failed to parse Questions JSON:", e);
                previewQuestionsContainer.innerHTML = '<p style="color: var(--error-color);">Error loading questions.</p>';
            }

            previewModal.classList.add('visible');
        });
    });
    
    // Close Modal Listener
    document.querySelectorAll('[data-modal-close="#previewActivityModal"]').forEach(btn => {
        btn.addEventListener('click', () => previewModal.classList.remove('visible'));
    });

    /* ------------------------------------------------------------------
       3. Approve Activity Logic
    ------------------------------------------------------------------ */
    const approveDialog = document.getElementById('approveConfirmDialog');
    const activityToApproveID = document.getElementById('activityToApproveID');
    const cancelApproveBtn = document.getElementById('cancelApprove');

    document.querySelectorAll('.approve-activity-btn').forEach(button => {
        button.addEventListener('click', function() {
            activityToApproveID.value = this.dataset.id;
            approveDialog.style.display = 'flex';
        });
    });

    cancelApproveBtn?.addEventListener('click', () => {
        approveDialog.style.display = 'none';
    });

    /* ------------------------------------------------------------------
       4. Reject Activity Logic
    ------------------------------------------------------------------ */
    const rejectModal = document.getElementById('rejectActivityModal');
    const activityToRejectID = document.getElementById('activityToRejectID');

    document.querySelectorAll('.reject-activity-btn').forEach(button => {
        button.addEventListener('click', function() {
            activityToRejectID.value = this.dataset.id;
            document.getElementById('admin_remarks').value = ''; // Clear previous remarks
            rejectModal.classList.add('visible');
        });
    });

    document.querySelectorAll('[data-modal-close="#rejectActivityModal"]').forEach(btn => {
        btn.addEventListener('click', () => rejectModal.classList.remove('visible'));
    });

    /* ------------------------------------------------------------------
       5. Delete Confirmation Dialog Logic
    ------------------------------------------------------------------ */
    const deleteConfirmDialog = document.getElementById('deleteConfirmDialog');
    const activityToDeleteID = document.getElementById('activityToDeleteID');
    const cancelDeleteBtn = document.getElementById('cancelDelete');

    document.querySelectorAll('.delete-activity-btn').forEach(button => {
        button.addEventListener('click', function() {
            activityToDeleteID.value = this.dataset.id;
            deleteConfirmDialog.style.display = 'flex';
        });
    });

    cancelDeleteBtn?.addEventListener('click', () => {
        deleteConfirmDialog.style.display = 'none';
    });

    /* ------------------------------------------------------------------
       6. TABLE FILTERING LOGIC
    ------------------------------------------------------------------ */

    // PENDING TAB FILTERS
    function filterPendingTable() {
        const mentorFilter = document.getElementById('filter_pending_mentor')?.value || '';
        const courseFilter = document.getElementById('filter_pending_course')?.value || '';
        const searchFilter = document.getElementById('filter_pending_search')?.value || '';
        
        const table = document.getElementById('pendingTable');
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const titleText = cells[0]?.textContent.trim().toLowerCase() || '';
            const mentorText = cells[1]?.textContent.trim().toLowerCase() || '';
            const courseLessonText = cells[2]?.textContent.trim().toLowerCase() || '';
            
            let show = true;
            
            // Mentor filter
            if (mentorFilter && !mentorText.includes(mentorFilter.toLowerCase())) {
                show = false;
            }
            
            // Course filter
            if (courseFilter && show && !courseLessonText.includes(courseFilter.toLowerCase())) {
                show = false;
            }
            
            // Search filter
            if (searchFilter && show) {
                const searchTerm = searchFilter.toLowerCase();
                if (!titleText.includes(searchTerm) && !courseLessonText.includes(searchTerm)) {
                    show = false;
                }
            }
            
            row.style.display = show ? '' : 'none';
        });
    }

    document.getElementById('filter_pending_mentor')?.addEventListener('change', filterPendingTable);
    document.getElementById('filter_pending_course')?.addEventListener('change', filterPendingTable);
    document.getElementById('filter_pending_search')?.addEventListener('keyup', filterPendingTable);

    // APPROVED TAB FILTERS
    function filterApprovedTable() {
        const mentorFilter = document.getElementById('filter_approved_mentor')?.value || '';
        const courseFilter = document.getElementById('filter_approved_course')?.value || '';
        const searchFilter = document.getElementById('filter_approved_search')?.value || '';
        
        const table = document.getElementById('approvedTable');
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const titleText = cells[0]?.textContent.trim().toLowerCase() || '';
            const mentorText = cells[1]?.textContent.trim().toLowerCase() || '';
            const courseLessonText = cells[2]?.textContent.trim().toLowerCase() || '';
            
            let show = true;
            
            if (mentorFilter && !mentorText.includes(mentorFilter.toLowerCase())) {
                show = false;
            }
            
            if (courseFilter && show && !courseLessonText.includes(courseFilter.toLowerCase())) {
                show = false;
            }
            
            if (searchFilter && show) {
                const searchTerm = searchFilter.toLowerCase();
                if (!titleText.includes(searchTerm) && !courseLessonText.includes(searchTerm)) {
                    show = false;
                }
            }
            
            row.style.display = show ? '' : 'none';
        });
    }

    document.getElementById('filter_approved_mentor')?.addEventListener('change', filterApprovedTable);
    document.getElementById('filter_approved_course')?.addEventListener('change', filterApprovedTable);
    document.getElementById('filter_approved_search')?.addEventListener('keyup', filterApprovedTable);

    // ALL TAB FILTERS
    function filterAllTable() {
        const mentorFilter = document.getElementById('filter_all_mentor')?.value || '';
        const statusFilter = document.getElementById('filter_all_status')?.value || '';
        const searchFilter = document.getElementById('filter_all_search')?.value || '';
        
        const table = document.getElementById('allTable');
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const titleText = cells[0]?.textContent.trim().toLowerCase() || '';
            const mentorText = cells[1]?.textContent.trim().toLowerCase() || '';
            const courseLessonText = cells[2]?.textContent.trim().toLowerCase() || '';
            const statusText = cells[4]?.textContent.trim().toLowerCase() || '';
            
            let show = true;
            
            if (mentorFilter && !mentorText.includes(mentorFilter.toLowerCase())) {
                show = false;
            }
            
            if (statusFilter && show && !statusText.includes(statusFilter.toLowerCase())) {
                show = false;
            }
            
            if (searchFilter && show) {
                const searchTerm = searchFilter.toLowerCase();
                if (!titleText.includes(searchTerm) && !courseLessonText.includes(searchTerm)) {
                    show = false;
                }
            }
            
            row.style.display = show ? '' : 'none';
        });
    }

    document.getElementById('filter_all_mentor')?.addEventListener('change', filterAllTable);
    document.getElementById('filter_all_status')?.addEventListener('change', filterAllTable);
    document.getElementById('filter_all_search')?.addEventListener('keyup', filterAllTable);

    console.log('✅ Admin Activities system initialized');
});