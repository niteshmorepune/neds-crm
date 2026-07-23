<x-app-layout>
    <x-slot name="header">Log a Call</x-slot>

    <div class="max-w-2xl mx-auto">
        <form method="POST" action="{{ route('calls.store') }}" enctype="multipart/form-data"
              class="rounded-lg bg-white p-6 shadow-sm grid grid-cols-1 gap-4 md:grid-cols-2"
              x-data="{
                  outcome: '{{ old('outcome', '') }}',
                  showFollowUp: {{ old('follow_up_at') ? 'true' : 'false' }},
                  dictating: false,
                  dictationSupported: 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window,
                  dictationLang: localStorage.getItem('dictationLang') || 'en-IN',
                  recognition: null,
                  recordingSupported: !!(navigator.mediaDevices && window.MediaRecorder),
                  recording: false,
                  recordingError: null,
                  hasVoiceNote: false,
                  audioUrl: null,
                  mediaRecorder: null,
                  audioChunks: [],
                  setDictationLang(lang) {
                      this.dictationLang = lang;
                      localStorage.setItem('dictationLang', lang);
                  },
                  async toggleRecording() {
                      if (! this.recordingSupported) return;
                      if (this.recording) {
                          this.mediaRecorder.stop();
                          return;
                      }
                      this.recordingError = null;
                      try {
                          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                          this.audioChunks = [];
                          this.mediaRecorder = new MediaRecorder(stream);
                          this.mediaRecorder.ondataavailable = (e) => this.audioChunks.push(e.data);
                          this.mediaRecorder.onerror = (e) => {
                              this.recordingError = 'Recording failed — ' + (e.error?.message || 'please try again.');
                              this.recording = false;
                              stream.getTracks().forEach((t) => t.stop());
                          };
                          this.mediaRecorder.onstop = () => {
                              const mimeType = this.mediaRecorder.mimeType || 'audio/webm';
                              const blob = new Blob(this.audioChunks, { type: mimeType });
                              if (blob.size === 0) {
                                  this.recordingError = 'No audio was captured — please try recording again.';
                                  stream.getTracks().forEach((t) => t.stop());
                                  return;
                              }
                              const ext = mimeType.includes('ogg') ? 'ogg' : 'webm';
                              const file = new File([blob], `voice-note.${ext}`, { type: mimeType });
                              const dt = new DataTransfer();
                              dt.items.add(file);
                              this.$refs.voiceNoteInput.files = dt.files;
                              this.audioUrl = URL.createObjectURL(blob);
                              this.hasVoiceNote = true;
                              stream.getTracks().forEach((t) => t.stop());
                          };
                          this.mediaRecorder.start();
                          this.recording = true;
                      } catch (err) {
                          this.recordingError = err.name === 'NotAllowedError'
                              ? 'Microphone access was blocked — allow it in your browser settings and try again.'
                              : 'Could not start recording — ' + (err.message || 'please try again.');
                          this.recording = false;
                      }
                  },
                  clearVoiceNote() {
                      this.$refs.voiceNoteInput.value = '';
                      this.audioUrl = null;
                      this.hasVoiceNote = false;
                  },
                  toggleDictation() {
                      if (! this.dictationSupported) return;
                      if (this.dictating) {
                          this.recognition?.stop();
                          return;
                      }
                      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                      this.recognition = new SpeechRecognition();
                      this.recognition.lang = this.dictationLang;
                      this.recognition.continuous = true;
                      this.recognition.interimResults = false;
                      this.recognition.onresult = (event) => {
                          let transcript = '';
                          for (let i = event.resultIndex; i < event.results.length; i++) {
                              if (event.results[i].isFinal) {
                                  transcript += event.results[i][0].transcript;
                              }
                          }
                          transcript = transcript.trim();
                          if (transcript) {
                              const notes = document.getElementById('notes');
                              notes.value = (notes.value.trim() ? notes.value.trim() + ' ' : '') + transcript;
                          }
                      };
                      this.recognition.onerror = () => { this.dictating = false; };
                      this.recognition.onend = () => { this.dictating = false; };
                      this.recognition.start();
                      this.dictating = true;
                  },
              }"
              x-init="$watch('outcome', val => {
                  if (['no_answer','busy','follow_up_needed'].includes(val)) showFollowUp = true;
              })">
            @csrf
            <div>
                <x-input-label for="customer_id" value="Client" />
                <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">—</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_id', $selectedCustomer) === $customer->id)>{{ $customer->company_name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($leads->isNotEmpty())
                <div>
                    <x-input-label for="lead_id" value="…or Lead" />
                    <select id="lead_id" name="lead_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">—</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}" @selected((int) old('lead_id', $selectedLead) === $lead->id)>{{ $lead->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <x-input-label for="direction" value="Direction *" />
                <select id="direction" name="direction" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @foreach ($directions as $d)<option value="{{ $d->value }}" @selected(old('direction') === $d->value)>{{ $d->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-input-label for="outcome" value="Outcome *" />
                <select id="outcome" name="outcome" x-model="outcome" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @foreach ($outcomes as $o)<option value="{{ $o->value }}" @selected(old('outcome') === $o->value)>{{ $o->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-input-label for="duration_minutes" value="Duration (mins)" />
                <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="0" class="mt-1 block w-full" :value="old('duration_minutes')" />
            </div>
            <div>
                <x-input-label for="called_at" value="When *" />
                <x-text-input id="called_at" name="called_at" type="datetime-local" class="mt-1 block w-full"
                    :value="old('called_at', now()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i'))" />
                <x-input-error :messages="$errors->get('called_at')" class="mt-1" />
            </div>
            <div class="md:col-span-2">
                <div class="flex items-center justify-between">
                    <x-input-label for="notes" value="Notes" />
                    <div x-show="dictationSupported" x-cloak class="flex items-center gap-2">
                        <select x-model="dictationLang" @change="setDictationLang($event.target.value)"
                                class="rounded-md border-gray-300 py-1 pl-2 pr-6 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="en-IN">English</option>
                            <option value="hi-IN">Hindi</option>
                            <option value="mr-IN">Marathi</option>
                        </select>
                        <button type="button" @click="toggleDictation()"
                                class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium transition-colors"
                                :class="dictating ? 'bg-red-50 text-red-600' : 'text-indigo-600 hover:bg-indigo-50'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 003-3V6a3 3 0 10-6 0v6a3 3 0 003 3z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0M12 18v3" />
                            </svg>
                            <span x-show="!dictating">Dictate</span>
                            <span x-show="dictating">Listening… (click to stop)</span>
                        </button>
                    </div>
                </div>
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes') }}</textarea>
                <p x-show="dictationSupported" x-cloak class="mt-1 text-xs text-gray-400">Speak your notes instead of typing in the language selected above — review and edit before saving.</p>

                @if ($aiEnabled)
                    <input type="file" name="voice_note" x-ref="voiceNoteInput" class="hidden" accept="audio/*">
                    <div x-show="recordingSupported" x-cloak class="mt-2 flex items-center gap-2">
                        <button type="button" @click="toggleRecording()"
                                class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium transition-colors"
                                :class="recording ? 'bg-red-50 text-red-600' : 'text-indigo-600 hover:bg-indigo-50'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 003-3V6a3 3 0 10-6 0v6a3 3 0 003 3z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0M12 18v3" />
                            </svg>
                            <span x-show="!recording">Record voice note (Hindi/Marathi/English)</span>
                            <span x-show="recording">Recording… (click to stop)</span>
                        </button>
                        <audio x-show="hasVoiceNote" x-cloak :src="audioUrl" controls class="h-8"></audio>
                        <button type="button" x-show="hasVoiceNote" x-cloak @click="clearVoiceNote()" class="text-xs text-gray-400 hover:text-gray-600">Remove</button>
                    </div>
                    <p x-show="recordingSupported && !recordingError" x-cloak class="mt-1 text-xs text-gray-400">We'll transcribe and translate it to English automatically — usually within a minute of saving.</p>
                    <p x-show="recordingError" x-cloak x-text="recordingError" class="mt-1 text-xs text-red-500"></p>
                @endif
            </div>

            {{-- Follow-up reminder --}}
            <div class="md:col-span-2 border-t border-gray-100 pt-4">
                <button type="button" @click="showFollowUp = !showFollowUp"
                        class="flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    <span x-text="showFollowUp ? '▼' : '▶'"></span>
                    <span x-text="showFollowUp ? 'Follow-up reminder' : 'Add follow-up reminder'"></span>
                </button>

                <div x-show="showFollowUp" x-transition class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="follow_up_at" value="Remind me on" />
                        <x-text-input id="follow_up_at" name="follow_up_at" type="datetime-local" class="mt-1 block w-full"
                            :value="old('follow_up_at')" />
                        <x-input-error :messages="$errors->get('follow_up_at')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="next_action" value="Next action" />
                        <x-text-input id="next_action" name="next_action" type="text" class="mt-1 block w-full"
                            placeholder="e.g. Send proposal, Call back at 3 PM"
                            :value="old('next_action')" />
                        <x-input-error :messages="$errors->get('next_action')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="md:col-span-2 flex items-center justify-end gap-3">
                <a href="{{ route('calls.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button>Log Call</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
