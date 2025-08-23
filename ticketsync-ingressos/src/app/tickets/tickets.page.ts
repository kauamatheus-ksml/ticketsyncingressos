// src/app/tickets/tickets.page.ts
import { Component, OnInit } from '@angular/core';
import { IonicModule } from '@ionic/angular';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { ToastController } from '@ionic/angular';

export interface Ticket {
  order_id: string;
  nome: string;
  sobrenome: string;
  email: string;
  valor_total: number;
  created_at: string;
  ticket_code: string | null;
  status: string;
}

@Component({
  selector: 'app-tickets',
  standalone: true,
  imports: [CommonModule, IonicModule],
  templateUrl: './tickets.page.html',
  styleUrls: ['./tickets.page.scss']
})
export class TicketsPage implements OnInit {
  userEmail: string = '';
  clienteNome: string = '';
  tickets: Ticket[] = [];
  loading: boolean = true;

  // Altere para o endpoint real que retorna os ingressos do cliente
  private API_TICKETS = 'https://ticketsync.com.br/list_ingressos_cliente.php';

  constructor(
    private route: ActivatedRoute,
    private http: HttpClient,
    private toastCtrl: ToastController
  ) {}

  ngOnInit() {
    this.route.queryParams.subscribe(params => {
      this.userEmail = params['userEmail'] || '';
      this.clienteNome = params['clienteNome'] || 'Cliente';
      if (this.userEmail) {
        this.fetchTickets();
      } else {
        this.loading = false;
      }
    });
  }

  fetchTickets() {
    this.http.get<Ticket[]>(`${this.API_TICKETS}?email=${encodeURIComponent(this.userEmail)}`)
      .subscribe(async data => {
        console.log('Ingressos recebidos:', data);
        this.tickets = data.map(item => ({
          ...item,
          valor_total: parseFloat(item.valor_total.toString())
        }));
        this.loading = false;
      }, async err => {
        this.loading = false;
        const toast = await this.toastCtrl.create({
          message: 'Não foi possível carregar os ingressos.',
          duration: 2000,
          color: 'danger'
        });
        toast.present();
      });
  }
}
