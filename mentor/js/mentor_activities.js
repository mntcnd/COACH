// File: js/mentor_activities.js
document.addEventListener('DOMContentLoaded', function() {

    /* ------------------------------------------------------------------
       1. Sidebar Toggle Logic
    ------------------------------------------------------------------ */
    const body = document.body;
    const toggleBtn = document.querySelector(".menu-icon");

    toggleBtn?.addEventListener("click", () => {
        body.classList.toggle("sidebar-collapsed");
        
        if (body.classList.contains("sidebar-collapsed")) {
            localStorage.setItem("sidebar-collapsed", "true");
        } else {
            localStorage.removeItem("sidebar-collapsed");
        }
    });

    /* ------------------------------------------------------------------
       2. Tab Switching Logic
    ------------------------------------------------------------------ */
    const tabs = document.querySelectorAll('.tab-item');
    const tabContents = document.querySelectorAll('.tab-content');
    const bodyElement = document.body;
    const initialTab = bodyElement.getAttribute('data-initial-tab') || 'create';

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
       3. Dynamic Question Builder Logic 
    ------------------------------------------------------------------ */
    const questionsContainer = document.getElementById('questions-container');
    const addQuestionBtn = document.getElementById('addQuestionBtn');
    const activityForm = document.getElementById('activityForm');
    const questionsJsonInput = document.getElementById('questionsJsonInput');
    const questionErrorMessage = document.getElementById('question-error-msg');
    let questionCounter = 0;

    function getQuestionTemplate(index, type = 'Multiple Choice', data = {}) {
        const isMC = type === 'Multiple Choice';
        const isID = type === 'Identification';
        const questionText = data.question || '';
        const choices = Array.isArray(data.choices) ? data.choices : ['', '', '', '']; 
        const correctAns = data.correct_answer || '';
        const acceptableAns = Array.isArray(data.acceptable_answers) ? data.acceptable_answers.join(', ') : (data.acceptable_answers || '');

        return `
            <div class="question-card" data-index="${index}">
                <div class="card-header">
                    <h4>Question #${index + 1}</h4>
                    <div class="question-actions">
                        <select class="question-type-select">
                            <option value="Multiple Choice" ${isMC ? 'selected' : ''}>Multiple Choice</option>
                            <option value="Identification" ${isID ? 'selected' : ''}>Identification</option>
                        </select>
                        <button type="button" class="action-btn delete-question-btn" title="Remove Question">
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Question Text:</label>
                    <textarea class="question-text-input" rows="3" placeholder="Enter the question...">${questionText}</textarea>
                </div>

                <div class="type-fields mc-fields" style="display:${isMC ? 'block' : 'none'};">
                    <div class="form-row choices-grid">
                        ${[1,2,3,4].map(i => `
                            <div class="form-group">
                                <label>Choice ${i}:</label>
                                <input type="text" class="choice-input" data-choice="${i}" value="${choices[i-1] || ''}" placeholder="Option ${i}">
                            </div>
                        `).join('')}
                    </div>
                    <div class="form-group form-row half">
                        <label>Correct Answer:</label>
                        <select class="correct-answer-select">
                            <option value="">Select the correct choice</option>
                            ${['Choice 1', 'Choice 2', 'Choice 3', 'Choice 4'].map(c => `
                                <option value="${c}" ${correctAns === c ? 'selected' : ''}>${c}</option>
                            `).join('')}
                        </select>
                    </div>
                </div>
                
                <div class="type-fields id-fields" style="display:${isID ? 'block' : 'none'};">
                    <div class="form-group">
                        <label>Acceptable Answers (Comma Separated):</label>
                        <input type="text" class="acceptable-answers-input" value="${acceptableAns}" placeholder="e.g., Apple, Aple, The Apple">
                    </div>
                </div>
            </div>
        `;
    }

    // Function to rebuild the entire form data and update the hidden JSON input
// Function to rebuild the entire form data and update the hidden JSON input
function updateQuestionsJson() {
    const questionsData = [];
    let isValid = true;
    
    // 💡 FIX: Get the list of all question cards (DOM elements)
    const allQuestionCards = questionsContainer.querySelectorAll('.question-card');

    allQuestionCards.forEach(card => {
        const type = card.querySelector('.question-type-select').value;
        const question = card.querySelector('.question-text-input').value.trim();
        const data = {
            type: type,
            question: question,
        };
        
        if (question === '') {
            isValid = false;
            card.style.border = '2px solid var(--error-color)';
            return;
        } else {
            card.style.border = ''; // Reset border
        }

        if (type === 'Multiple Choice') {
            const choices = [];
            let allChoicesEmpty = true;
            card.querySelectorAll('.choice-input').forEach(input => {
                const choiceValue = input.value.trim();
                choices.push(choiceValue);
                if (choiceValue !== '') {
                    allChoicesEmpty = false;
                }
            });
            
            const correctAns = card.querySelector('.correct-answer-select').value;
            
            data.choices = choices;
            data.correct_answer = correctAns;
            
            if (allChoicesEmpty || correctAns === '') {
                isValid = false;
                card.style.border = '2px solid var(--error-color)';
                return;
            }
        } else if (type === 'Identification') {
            const acceptableAns = card.querySelector('.acceptable-answers-input').value.trim();
            data.acceptable_answers = acceptableAns.split(',').map(a => a.trim()).filter(a => a !== '');

            if (data.acceptable_answers.length === 0) {
                isValid = false;
                card.style.border = '2px solid var(--error-color)';
                return;
            }
        }
        
        questionsData.push(data);
    });
    
    // ----------------------------------------------------------------
    // 🟢 CORRECTED VALIDATION LOGIC
    // ----------------------------------------------------------------
    
    // 1. Check for existence based on the DOM count (allQuestionCards.length).
    if (allQuestionCards.length === 0) {
        questionErrorMessage.style.display = 'block';
        questionErrorMessage.textContent = "❌ Please add at least one question.";
        isValid = false;
    } 
    // 2. If questions exist but content validation failed (isValid is false).
    else if (!isValid) {
        // We prevent the form submission (via 'return isValid' below), 
        // but hide the misleading 'add at least one question' error.
        questionErrorMessage.style.display = 'none';
        // We still populate the JSON with any valid questions for continuity
        questionsJsonInput.value = JSON.stringify(questionsData); 
    } 
    // 3. If questions exist and all content is valid.
    else {
        questionsJsonInput.value = JSON.stringify(questionsData);
        questionErrorMessage.style.display = 'none';
    }

    return isValid;
}

    // Event listener to attach to the form submission
    activityForm?.addEventListener('submit', function(e) {
        // Only run validation if not deleting
        if (!e.submitter || !e.submitter.name || e.submitter.name !== 'delete_activity_id') {
            if (!updateQuestionsJson()) {
                e.preventDefault();
                // Scroll to the top of the form or the error message
                questionsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });

    // Event Delegation for Question Card Actions
    questionsContainer?.addEventListener('click', function(e) {
        const card = e.target.closest('.question-card');
        if (!card) return;

        if (e.target.closest('.delete-question-btn')) {
            card.remove();
            reindexQuestions();

        }
    });

// Event Delegation for Question Type Change
questionsContainer?.addEventListener('change', function(e) {
    if (e.target.classList.contains('question-type-select')) {
        const card = e.target.closest('.question-card');
        const type = e.target.value;
        
        const mcFields = card.querySelector('.mc-fields');
        const idFields = card.querySelector('.id-fields');

        if (type === 'Multiple Choice') {
            mcFields.style.display = 'block';
            idFields.style.display = 'none';
        } else if (type === 'Identification') {
            mcFields.style.display = 'none';
            idFields.style.display = 'block';
        }
        // Validation (updateQuestionsJson()) is intentionally removed here.
        // It will now only run on form submission, ensuring a smooth transition.
    }
});
    
    // Helper to re-label questions after delete
    function reindexQuestions() {
        questionCounter = 0;
        questionsContainer.querySelectorAll('.question-card').forEach((card, index) => {
            card.dataset.index = index;
            card.querySelector('h4').textContent = `Question #${index + 1}`;
            questionCounter++;
        });
    }

    // Add Question Button Handler
    addQuestionBtn?.addEventListener('click', function() {
        questionsContainer.insertAdjacentHTML('beforeend', getQuestionTemplate(questionCounter));
        questionCounter++;
    });

 // --- Edit Activity Logic ---
document.querySelectorAll('.edit-activity-btn').forEach(button => {
    button.addEventListener('click', function() {
        const data = this.dataset;

        // 1. Set form data
        document.getElementById('activity_id').value = data.id;
        document.getElementById('course_id').value = data.courseId;
        document.getElementById('lesson').value = data.lesson;
        document.getElementById('activity_title').value = data.title;
        document.getElementById('current_file_path').value = data.file;
        
        // 2. Handle File Display
        const currentFileDisplay = document.getElementById('currentFileDisplay');
        const currentFileLink = document.getElementById('currentFileLink');
        const fileInstruction = document.getElementById('file_instruction');

        if (data.file && data.file !== 'null' && data.file !== '') {
            currentFileLink.href = data.file;
            currentFileLink.textContent = data.file.split('/').pop();
            currentFileDisplay.style.display = 'block';
            fileInstruction.textContent = 'A new file will replace the current one. Leave empty to keep.';
        } else {
            currentFileDisplay.style.display = 'none';
            fileInstruction.textContent = 'Upload a reference file (PDF, JPG, PNG) if necessary (Max 50MB).';
        }
        
        // 3. Clear and rebuild questions
        questionsContainer.innerHTML = '';
        questionCounter = 0;
        
        try {
            const questions = JSON.parse(data.questions);
            questions.forEach((q, index) => {
                questionsContainer.insertAdjacentHTML('beforeend', getQuestionTemplate(index, q.type, q));
                questionCounter++;
            });
        } catch (e) {
            console.error("Failed to parse Questions JSON for edit:", e);
            questionsContainer.insertAdjacentHTML('beforeend', getQuestionTemplate(0));
            questionCounter = 1;
        }

        // 4. Update button text and show Cancel button
        const submitBtn = activityForm.querySelector('.gradient-button');
        submitBtn.innerHTML = "<i class='bx bx-refresh'></i> Update & Resubmit";
        
        // Create cancel button if it doesn't exist
        let cancelBtn = document.getElementById('cancelEditBtn');
        if (!cancelBtn) {
            cancelBtn = document.createElement('button');
            cancelBtn.id = 'cancelEditBtn';
            cancelBtn.type = 'button';
            cancelBtn.className = 'gradient-button cancel';
            cancelBtn.innerHTML = "<i class='bx bx-x'></i> Cancel";
            submitBtn.insertAdjacentElement('afterend', cancelBtn);
        }

        // 5. Change form title
        document.querySelector('#create-tab .card-title').textContent = "Edit Activity (ID: " + data.id + ")";
        document.querySelector('.tab-content.active').scrollIntoView({ behavior: 'smooth' });
        activateTab('create');

        // 6. Cancel button logic
        cancelBtn.onclick = () => {
            // Clear form fields
            activityForm.reset();
            document.getElementById('activity_id').value = '';
            document.getElementById('current_file_path').value = '';

            // Reset questions
            questionsContainer.innerHTML = getQuestionTemplate(0);
            questionCounter = 1;

            // Reset display
            currentFileDisplay.style.display = 'none';
            fileInstruction.textContent = 'Upload a reference file (PDF, JPG, PNG) if necessary (Max 50MB).';

            // Restore title and button
            document.querySelector('#create-tab .card-title').textContent = "Create Activity";
            submitBtn.innerHTML = "<i class='bx bx-send'></i> Submit for Approval";
                                            

            // Remove cancel button
            cancelBtn.remove();
        };
    });
});

    
    // Remove File Button Logic
    document.getElementById('removeFileBtn')?.addEventListener('click', function() {
        document.getElementById('current_file_path').value = ''; // Clear the path
        document.getElementById('activity_file').value = ''; // Clear the file input
        document.getElementById('currentFileDisplay').style.display = 'none'; // Hide the display
        document.getElementById('file_instruction').textContent = 'Upload a reference file (PDF, JPG, PNG) if necessary (Max 50MB).';
    });


    /* ------------------------------------------------------------------
       4. Activity Preview Modal Logic (NEW FEATURE)
    ------------------------------------------------------------------ */
    const previewModal = document.getElementById('previewActivityModal');
    const previewTitle = document.getElementById('previewActivityName'); // Changed from previewModalTitle to previewActivityName
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
            previewCourse.textContent = data.course;
            previewLesson.textContent = data.lesson;
            
            // Status Tag
            previewStatus.textContent = data.status;
            previewStatus.className = 'status-tag ' + data.status.toLowerCase().replace(' ', '-');

            // File Link (Mentor Side Preview)
if (data.file && data.file.trim() !== '' && data.file !== 'null') {
    // Build full view URL for mentors
    const filePath = encodeURIComponent(data.file); // prevent path issues
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
    
    // Close Modal Listener (using data-modal-close from PHP HTML)
    document.querySelectorAll('[data-modal-close="#previewActivityModal"]').forEach(btn => {
        btn.addEventListener('click', () => previewModal.classList.remove('visible'));
    });


    /* ------------------------------------------------------------------
       5. Assignment Modal Logic
    ------------------------------------------------------------------ */
    const assignModal = document.getElementById('assignActivityModal');
    const assignModalTitle = document.getElementById('assignModalTitle');
    const activityToAssignID = document.getElementById('activityToAssignID');
    const toggleAllMentees = document.getElementById('toggleAllMentees');
    const menteeCheckboxes = document.querySelectorAll('#assignForm input[type="checkbox"]');

    document.querySelectorAll('.assign-activity-btn').forEach(button => {
        button.addEventListener('click', function() {
            const activityTitle = this.closest('tr').querySelector('[data-label="Title"]').textContent;
            assignModalTitle.textContent = activityTitle;
            activityToAssignID.value = this.dataset.id;
            assignModal.classList.add('visible');
            
            // Reset checkboxes when opening
            menteeCheckboxes.forEach(checkbox => checkbox.checked = false);
            toggleAllMentees.textContent = 'Select All';
        });
    });

    document.querySelectorAll('[data-modal-close="#assignActivityModal"]').forEach(btn => {
        btn.addEventListener('click', () => assignModal.classList.remove('visible'));
    });
    
    toggleAllMentees?.addEventListener('click', function() {
        const allSelected = this.textContent === 'Deselect All';
        menteeCheckboxes.forEach(checkbox => checkbox.checked = !allSelected);
        this.textContent = allSelected ? 'Select All' : 'Deselect All';
    });


    /* ------------------------------------------------------------------
       6. Delete Confirmation Dialog Logic
    ------------------------------------------------------------------ */
    const deleteConfirmDialog = document.getElementById('deleteConfirmDialog');
    const activityToDeleteID = document.getElementById('activityToDeleteID');
    const cancelDeleteBtn = document.getElementById('cancelDelete');

    document.querySelectorAll('.delete-activity-btn').forEach(button => {
        button.addEventListener('click', function() {
            activityToDeleteID.value = this.dataset.id;
            deleteConfirmDialog.style.display = 'flex'; // Using style.display for the small dialog
        });
    });

    cancelDeleteBtn?.addEventListener('click', () => {
        deleteConfirmDialog.style.display = 'none';
    });
    



/* ------------------------------------------------------------------
   7. Submission Review and Re-grading Logic (NEW FEATURE)
------------------------------------------------------------------ */
const regradeModal = document.getElementById('submissionRegradeModal');

// Helper to check if an answer is automatically correct based on the Questions_JSON structure
function isAnswerCorrect(question, menteeAnswer) {
    const type = question.type;
    const menteeResponse = String(menteeAnswer).trim().toLowerCase();

    if (menteeResponse === 'no answer provided') {
        return false;
    }

    if (type === 'Multiple Choice') {
        // 1. Get the correct index from "Choice X" (e.g., "Choice 1")
        const correctChoiceString = String(question.correct_answer || '').trim();
        const match = correctChoiceString.match(/Choice (\d+)/i);
        
        if (!match) {
             return false; // Format error in Questions_JSON
        }

        // Calculate the 0-based index (e.g., Choice 1 -> index 0)
        const correctChoiceIndex = parseInt(match[1]) - 1; 
        
        // 2. Convert the correct index (0, 1, 2...) to the correct letter (A, B, C...)
        const correctLetter = String.fromCharCode('A'.charCodeAt(0) + correctChoiceIndex);
        
        // 3. Compare mentee's single-letter answer (A, B, C...) to the derived correct letter
        return menteeResponse.toUpperCase() === correctLetter;

    } else if (type === 'Identification') {
        // 1. Use the 'acceptable_answers' array
        const acceptableAnswers = Array.isArray(question.acceptable_answers) ? question.acceptable_answers : [];
        
        if (acceptableAnswers.length === 0) {
            return false; // Cannot automatically grade
        }
        
        // 2. Check if the mentee's answer matches any acceptable answer (case-insensitive)
        return acceptableAnswers.some(acceptable => 
            String(acceptable).trim().toLowerCase() === menteeResponse
        );
    }
    
    // For Essay type, or if the logic doesn't match above, default to manual check (false)
    return false;
}

// Helper to render the Q&A view inside the modal
function renderRegradeView(questions, answers) {
    const container = document.getElementById('regradeQuestionsContainer');
    container.innerHTML = '';
    
    if (!Array.isArray(questions) || questions.length === 0) {
        container.innerHTML = '<p style="color: var(--error-color);">Error: Questions data is missing or corrupted.</p>';
        return;
    }
    
    // Answers are stored as an object (e.g., {answer_0: "A", answer_1: "static"})
    const answersObject = (answers && typeof answers === 'object' && !Array.isArray(answers)) ? answers : {};
    
    questions.forEach((q, index) => {
        
        const answerKey = `answer_${index}`;
        const menteeAnswer = answersObject[answerKey] || 'No Answer Provided';
        
        // --- Determine correctness and status classes (using the updated helper) ---
        const isCorrect = isAnswerCorrect(q, menteeAnswer);
        const statusClass = isCorrect ? 'correct-answer' : 'incorrect-answer';
        const statusIcon = isCorrect ? 'bx-check-circle' : 'bx-x-circle';
        // --- End status determination ---

        const isMC = q.type === 'Multiple Choice';
        let answerDisplay = '';
        const answerString = String(menteeAnswer);

        // --- Mentee Answer Display Logic (converts letter to text for MC) ---
        if (isMC && answerString.length === 1 && /[A-Z]/.test(answerString)) {
            const choiceIndex = answerString.charCodeAt(0) - 'A'.charCodeAt(0); 
            const choiceText = (q.choices && q.choices[choiceIndex]) ? q.choices[choiceIndex] : answerString;
            answerDisplay = `<span class="mentee-answer-text">**Choice ${answerString}**: ${choiceText}</span>`;
        } else {
            answerDisplay = `<span class="mentee-answer-text">${answerString}</span>`;
        }
        
        // --- Correct Answer Display Logic (Based on Questions_JSON format) ---
        let correctAnswerText = 'Manual checking required.';
        let isManualCheck = true;

        if (q.type === 'Multiple Choice' && q.correct_answer && q.choices) {
            const match = q.correct_answer.match(/Choice (\d+)/i);
            const correctIndex = match ? parseInt(match[1]) - 1 : -1;
            
            if (correctIndex >= 0 && q.choices[correctIndex]) {
                 const correctLetter = String.fromCharCode('A'.charCodeAt(0) + correctIndex);
                 const correctChoiceText = q.choices[correctIndex];
                 correctAnswerText = `Choice ${correctLetter}: ${correctChoiceText}`;
                 isManualCheck = false;
            }
        } else if (q.type === 'Identification' && Array.isArray(q.acceptable_answers) && q.acceptable_answers.length > 0) {
             correctAnswerText = q.acceptable_answers.join(' OR ');
             isManualCheck = false;
        }
        
        const correctAnswerDisplay = `<p class="correct-answer-text ${isManualCheck ? 'manual-check' : ''}">
            <strong>Correct Answer:</strong> ${correctAnswerText}
        </p>`;
        // --- End Correct Answer Display ---

        const html = `
            <div class="review-question-block">
                <p class="question-title"><strong>Question ${index + 1}:</strong> ${q.question}</p>
                <div class="mentee-answer-box ${statusClass}">
                    <p class="mentee-answer-header">
                        <strong>Mentee's Answer (${q.type}):</strong> 
                        <i class='bx ${statusIcon} status-icon' title="${isCorrect ? 'Automatically Correct' : 'Automatically Incorrect'}"></i>
                    </p>
                    ${answerDisplay}
                    ${correctAnswerDisplay} 
                </div>
            </div>
            <hr class="review-separator">
        `;
        container.insertAdjacentHTML('beforeend', html);
    });
    
    if (questions.length > 0) {
       const separators = container.querySelectorAll('.review-separator');
       separators[separators.length - 1]?.remove();
    }
}

// --- Global Toast Message Function ---
function showToastMessage(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.classList.add('toast-message', type);
    // Use innerHTML to allow icons or bold text if needed
    toast.innerHTML = `<i class='bx bx-${type === 'success' ? 'check-circle' : 'error-alt'}' style='margin-right: 8px;'></i> ${message}`; 

    // Add to container
    container.appendChild(toast);

    // Show with transition
    setTimeout(() => {
        toast.classList.add('visible');
    }, 10);

    // Hide and remove after 4 seconds
    setTimeout(() => {
        toast.classList.remove('visible');
        // Remove from DOM after transition finishes
        toast.addEventListener('transitionend', () => {
            toast.remove();
        });
    }, 4000);
}


document.querySelectorAll('.regrade-submission-btn').forEach(button => {
    button.addEventListener('click', function() {
        const data = this.dataset;
        
        // --- 1. Determine the score to display and grade against ---
        const originalScore = parseFloat(data.score) || 0;
        const finalScoreString = data.finalScore; 
        
        let scoreToUse = originalScore; // Default to the original score
        
        // Check if data.finalScore has ANY content (meaning it was explicitly set by the mentor).
        if (finalScoreString !== undefined && finalScoreString !== null && finalScoreString.trim() !== '') {
            const currentFinalScore = parseFloat(finalScoreString);
            
            // If the final score is a valid number (e.g., 5, 10, or 0)
            if (!isNaN(currentFinalScore)) { 
                scoreToUse = currentFinalScore;
            }
        }
            
        // Use toFixed(2) to ensure a float-like display ("10.00" or "2.00")
        const formattedScore = scoreToUse.toFixed(2); 


        // 2. Set Header Details 
        document.getElementById('reviewActivityTitle').textContent = data.activity;
        document.getElementById('reviewMenteeName').textContent = data.mentee;
        document.getElementById('reviewLessonName').textContent = data.lesson;
        
        // 🟢 FIX: Set the header display to the calculated Final/Original score
        document.getElementById('reviewCurrentScore').textContent = formattedScore; 
        
        const statusTag = document.getElementById('reviewStatusTag');
        statusTag.textContent = data.status;
        statusTag.className = 'status-tag ' + data.status.toLowerCase().replace(' ', '-');
        
        
        // 3. Set Hidden Form Fields and Default Values for Grading
        document.getElementById('regradeSubmissionID').value = data.id;
        
        // 🟢 FIX: Set the input field VALUE to the calculated Final/Original score
        document.getElementById('overall_score').value = formattedScore;

        document.getElementById('overall_feedback').value = data.feedback;
        

        


        // 3. Handle File Link
        if (data.file && data.file !== 'null' && data.file !== '') {
            document.getElementById('reviewFileLink').href = data.file;
            document.getElementById('reviewFileLink').style.display = 'inline-flex';
            document.getElementById('noReviewFileMsg').style.display = 'none';
        } else {
            document.getElementById('reviewFileLink').style.display = 'none';
            document.getElementById('noReviewFileMsg').style.display = 'block';
        }

        // 4. Parse and Render Questions & Answers
        try {
            const questions = JSON.parse(data.questions);
            const answers = JSON.parse(data.answers);
            renderRegradeView(questions, answers);
        } catch (e) {
            console.error("Failed to parse Questions or Answers JSON:", e);
            document.getElementById('regradeQuestionsContainer').innerHTML = '<p style="color: var(--error-color);">Error loading questions and answers. Check the console for details.</p>';
        }

        regradeModal.classList.add('visible');
    });
});

// 7.2 Close Modal Listener
document.querySelectorAll('[data-modal-close="#submissionRegradeModal"]').forEach(btn => {
    btn.addEventListener('click', () => regradeModal.classList.remove('visible'));
});


// 7.3 AJAX Submission Handler (Updated with Toast Messages)
document.getElementById('regradeForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('.gradient-button');
    const originalText = submitBtn.innerHTML;
    const regradeModal = document.getElementById('submissionRegradeModal'); 

    // 1. SHOW LOADING STATE
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Saving Score...'; 

    fetch(form.action, {
        method: 'POST',
        body: formData,
    })
    .then(response => {
        return response.json();
    })
    .then(data => {
        // Close the modal immediately
        regradeModal.classList.remove('visible'); 
        
        // 2. SHOW SUCCESS OR ERROR MESSAGE
        if (data.success) {
            showToastMessage(data.message, 'success'); 
            
            // Reload the page after a short delay to allow the user to read the success message
            setTimeout(() => {
                // Reload with the 'submissions' tab active
                window.location.href = 'activities.php?active_tab=submissions'; 
            }, 1500); 

        } else {
            showToastMessage('Grade update failed: ' + data.message, 'error');
            
            // If failed, reset button for retry
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Submission error:', error);
        regradeModal.classList.remove('visible');
        showToastMessage('A network error occurred. Please try again.', 'error');
    })
    .finally(() => {
        // Only reset the button if the process didn't result in a successful reload flow
        // (This prevents the button from flashing back to normal right before the page reloads)
        if (!data || !data.success) {
             submitBtn.disabled = false;
             submitBtn.innerHTML = originalText;
        }
    });
});
/* ------------------------------------------------------------------
   8. CUSTOM TABLE FILTERING LOGIC
------------------------------------------------------------------ */

// PENDING TAB FILTERS
document.getElementById('filter_pending_course')?.addEventListener('change', function() {
    const courseFilter = this.value;
    const searchFilter = document.getElementById('filter_pending_search').value;
    
    const table = document.getElementById('pendingTable');
    if (!table) {
        console.error('pendingTable not found');
        return;
    }
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const titleText = cells[0]?.textContent.trim().toLowerCase() || '';
        const courseLessonText = cells[1]?.textContent.trim().toLowerCase() || '';
        
        let show = true;
        
        // Course filter
        if (courseFilter && !courseLessonText.includes(courseFilter.toLowerCase())) {
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
});

document.getElementById('filter_pending_search')?.addEventListener('keyup', function() {
    // Trigger the course filter which handles both
    const courseFilter = document.getElementById('filter_pending_course');
    if (courseFilter) {
        courseFilter.dispatchEvent(new Event('change'));
    }
});

// APPROVED TAB FILTERS
document.getElementById('filter_approved_course')?.addEventListener('change', function() {
    const courseFilter = this.value;
    const searchFilter = document.getElementById('filter_approved_search').value;
    
    const table = document.getElementById('approvedTable');
    if (!table) {
        console.error('approvedTable not found');
        return;
    }
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const titleText = cells[0]?.textContent.trim().toLowerCase() || '';
        const courseLessonText = cells[1]?.textContent.trim().toLowerCase() || '';
        
        let show = true;
        
        // Course filter
        if (courseFilter && !courseLessonText.includes(courseFilter.toLowerCase())) {
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
});

document.getElementById('filter_approved_search')?.addEventListener('keyup', function() {
    // Trigger the course filter which handles both
    const courseFilter = document.getElementById('filter_approved_course');
    if (courseFilter) {
        courseFilter.dispatchEvent(new Event('change'));
    }
});

// SUBMISSIONS TAB FILTERS
function filterSubmissionsTable() {
    const menteeFilter = document.getElementById('filter_submissions_mentee')?.value || '';
    const statusFilter = document.getElementById('filter_submissions_status')?.value || '';
    const searchFilter = document.getElementById('filter_submissions_search')?.value || '';
    
    const table = document.getElementById('submissionsTable');
    if (!table) {
        console.error('submissionsTable not found');
        return;
    }
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const activityText = cells[0]?.textContent.trim().toLowerCase() || '';
        const menteeText = cells[1]?.textContent.trim().toLowerCase() || '';
        const statusText = cells[6]?.textContent.trim().toLowerCase() || '';
        
        let show = true;
        
        // Mentee filter
        if (menteeFilter && !menteeText.includes(menteeFilter.toLowerCase())) {
            show = false;
        }
        
        // Status filter
        if (statusFilter && show && !statusText.includes(statusFilter.toLowerCase())) {
            show = false;
        }
        
        // Search filter (activity/lesson)
        if (searchFilter && show) {
            const searchTerm = searchFilter.toLowerCase();
            if (!activityText.includes(searchTerm)) {
                show = false;
            }
        }
        
        row.style.display = show ? '' : 'none';
    });
}

document.getElementById('filter_submissions_mentee')?.addEventListener('change', filterSubmissionsTable);
document.getElementById('filter_submissions_status')?.addEventListener('change', filterSubmissionsTable);
document.getElementById('filter_submissions_search')?.addEventListener('keyup', filterSubmissionsTable);

console.log('✅ Filter system initialized');

});